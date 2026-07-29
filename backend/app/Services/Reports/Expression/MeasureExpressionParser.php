<?php

namespace App\Services\Reports\Expression;

use InvalidArgumentException;

/**
 * Recursive-descent lexer/parser — kapalı dilbilgisi (D1c).
 *
 * Gerekçe (symfony/expression-language yerine):
 * - Sadece aggregation + aritmetik + if/karşılaştırma whitelist'i
 * - Alan adları ayrı token; bilinmeyen fonksiyon/karakter hemen 422
 * - Ek bağımlılık yok; AST doğrudan SQL derleyicisine bağlanır
 */
final class MeasureExpressionParser
{
    public const AGG_FNS = ['sum', 'count', 'count_distinct', 'avg', 'min', 'max'];

    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    public function parse(string $input): ExprNode
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Boş ifade');
        }
        if (strlen($trimmed) > 2000) {
            throw new InvalidArgumentException('İfade çok uzun');
        }
        if (str_contains($trimmed, "\0")) {
            throw new InvalidArgumentException('İzin verilmeyen karakter');
        }
        $this->tokens = $this->tokenize($trimmed);
        $this->pos = 0;
        $node = $this->parseOr();
        if (! $this->eof()) {
            throw new InvalidArgumentException('Beklenmeyen token: '.$this->peek()['value']);
        }

        return $node;
    }

    /**
     * @return list<string> formülde geçen alan anahtarları
     */
    public function referencedFields(ExprNode $node): array
    {
        $out = [];
        $this->collectFields($node, $out);

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $out
     */
    private function collectFields(ExprNode $node, array &$out): void
    {
        if ($node instanceof FieldRefNode) {
            $out[] = $node->key;
        } elseif ($node instanceof AggCallNode) {
            if ($node->fieldKey !== null) {
                $out[] = $node->fieldKey;
            }
        } elseif ($node instanceof BinaryOpNode || $node instanceof CompareNode || $node instanceof LogicNode) {
            $this->collectFields($node->left, $out);
            $this->collectFields($node->right, $out);
        } elseif ($node instanceof IfNode) {
            $this->collectFields($node->condition, $out);
            $this->collectFields($node->then, $out);
            $this->collectFields($node->else, $out);
        }
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($input);
        while ($i < $len) {
            $ch = $input[$i];
            if (ctype_space($ch)) {
                $i++;
                continue;
            }
            // multi-char ops
            if ($i + 1 < $len) {
                $two = $input[$i].$input[$i + 1];
                if (in_array($two, ['!=', '>=', '<='], true)) {
                    $tokens[] = ['type' => 'op', 'value' => $two];
                    $i += 2;
                    continue;
                }
            }
            if (in_array($ch, ['+', '-', '*', '/', '(', ')', ',', '=', '>', '<'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $ch];
                $i++;
                continue;
            }
            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $len && ctype_digit($input[$i + 1]))) {
                $start = $i;
                while ($i < $len && (ctype_digit($input[$i]) || $input[$i] === '.')) {
                    $i++;
                }
                $num = substr($input, $start, $i - $start);
                if (! is_numeric($num) || substr_count($num, '.') > 1) {
                    throw new InvalidArgumentException('Geçersiz sayı: '.$num);
                }
                $tokens[] = ['type' => 'number', 'value' => $num];
                continue;
            }
            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($input[$i]) || $input[$i] === '_')) {
                    $i++;
                }
                $ident = substr($input, $start, $i - $start);
                $lower = strtolower($ident);
                if (in_array($lower, self::AGG_FNS, true) || $lower === 'if' || $lower === 'and' || $lower === 'or') {
                    $tokens[] = ['type' => 'keyword', 'value' => $lower];
                } else {
                    $tokens[] = ['type' => 'ident', 'value' => $ident];
                }
                continue;
            }
            throw new InvalidArgumentException('İzin verilmeyen karakter: '.$ch);
        }

        return $tokens;
    }

    private function parseOr(): ExprNode
    {
        $left = $this->parseAnd();
        while ($this->matchKeyword('or')) {
            $left = new LogicNode('or', $left, $this->parseAnd());
        }

        return $left;
    }

    private function parseAnd(): ExprNode
    {
        $left = $this->parseComparison();
        while ($this->matchKeyword('and')) {
            $left = new LogicNode('and', $left, $this->parseComparison());
        }

        return $left;
    }

    private function parseComparison(): ExprNode
    {
        $left = $this->parseAdd();
        $op = $this->matchAnyOp(['=', '!=', '>', '<', '>=', '<=']);
        if ($op !== null) {
            return new CompareNode($op, $left, $this->parseAdd());
        }

        return $left;
    }

    private function parseAdd(): ExprNode
    {
        $left = $this->parseMul();
        while (true) {
            $op = $this->matchAnyOp(['+', '-']);
            if ($op === null) {
                break;
            }
            $left = new BinaryOpNode($op, $left, $this->parseMul());
        }

        return $left;
    }

    private function parseMul(): ExprNode
    {
        $left = $this->parsePrimary();
        while (true) {
            $op = $this->matchAnyOp(['*', '/']);
            if ($op === null) {
                break;
            }
            $left = new BinaryOpNode($op, $left, $this->parsePrimary());
        }

        return $left;
    }

    private function parsePrimary(): ExprNode
    {
        if ($this->matchOp('(')) {
            $inner = $this->parseOr();
            $this->expectOp(')');

            return $inner;
        }

        if ($this->matchKeyword('if')) {
            $this->expectOp('(');
            $cond = $this->parseOr();
            $this->expectOp(',');
            $then = $this->parseOr();
            $this->expectOp(',');
            $else = $this->parseOr();
            $this->expectOp(')');

            return new IfNode($cond, $then, $else);
        }

        foreach (self::AGG_FNS as $fn) {
            if ($this->matchKeyword($fn)) {
                $this->expectOp('(');
                if ($fn === 'count' && $this->matchOp('*')) {
                    $this->expectOp(')');

                    return new AggCallNode('count', null);
                }
                $field = $this->expectIdent();
                $this->expectOp(')');

                return new AggCallNode($fn, $field);
            }
        }

        if (($t = $this->peek()) && $t['type'] === 'number') {
            $this->pos++;
            $v = $t['value'];

            return new NumberNode(str_contains($v, '.') ? (float) $v : (int) $v);
        }

        if (($t = $this->peek()) && $t['type'] === 'ident') {
            $this->pos++;

            return new FieldRefNode($t['value']);
        }

        throw new InvalidArgumentException('Beklenen ifade, bulunan: '.($this->peek()['value'] ?? 'EOF'));
    }

    /** @return array{type: string, value: string}|null */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function eof(): bool
    {
        return $this->pos >= count($this->tokens);
    }

    private function matchKeyword(string $kw): bool
    {
        $t = $this->peek();
        if ($t && $t['type'] === 'keyword' && $t['value'] === $kw) {
            $this->pos++;

            return true;
        }

        return false;
    }

    private function matchOp(string $op): bool
    {
        $t = $this->peek();
        if ($t && $t['type'] === 'op' && $t['value'] === $op) {
            $this->pos++;

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $ops
     */
    private function matchAnyOp(array $ops): ?string
    {
        $t = $this->peek();
        if ($t && $t['type'] === 'op' && in_array($t['value'], $ops, true)) {
            $this->pos++;

            return $t['value'];
        }

        return null;
    }

    private function expectOp(string $op): void
    {
        if (! $this->matchOp($op)) {
            throw new InvalidArgumentException("Beklenen '{$op}'");
        }
    }

    private function expectIdent(): string
    {
        $t = $this->peek();
        if (! $t || $t['type'] !== 'ident') {
            throw new InvalidArgumentException('Beklenen alan adı');
        }
        $this->pos++;

        return $t['value'];
    }
}
