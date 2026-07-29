<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\Expression\MeasureExpressionCompiler;
use App\Services\Reports\Expression\MeasureExpressionParser;
use App\Services\Reports\ReportField;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * D1c — DSL güvenlik: enjeksiyon / izinsiz alan / sıfıra bölme.
 */
class MeasureExpressionSecurityTest extends TestCase
{
    private MeasureExpressionParser $parser;

    private MeasureExpressionCompiler $compiler;

    /** @var array<string, ReportField> */
    private array $fieldMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MeasureExpressionParser;
        $this->compiler = new MeasureExpressionCompiler;
        $this->fieldMap = [
            'gross_salary' => new ReportField(
                key: 'gross_salary',
                column: 'employees.gross_salary',
                type: 'number',
                label: 'Brüt',
                labelKey: 'x',
                role: 'measure',
                permission: 'employees.salary.view',
                sensitive: true,
            ),
            'net_salary' => new ReportField(
                key: 'net_salary',
                column: 'employees.net_salary',
                type: 'number',
                label: 'Net',
                labelKey: 'x',
                role: 'measure',
            ),
            'status' => new ReportField(
                key: 'status',
                column: 'employees.status',
                type: 'string',
                label: 'Durum',
                labelKey: 'x',
                role: 'dimension',
            ),
        ];
    }

    #[DataProvider('injectionProvider')]
    public function test_injection_attempts_rejected(string $expr): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($expr);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function injectionProvider(): array
    {
        return [
            ['1;DROP TABLE employees'],
            ['sum(gross_salary); delete from employees'],
            ["sum(gross_salary) --"],
            ['sum(gross_salary) /* comment */'],
            ['sum(employees.gross_salary)'],
            ['sum("gross_salary")'],
            ["sum('gross_salary')"],
            ['exec(xp_cmdshell)'],
            ['pg_sleep(1)'],
            ['sum(gross_salary) union select 1'],
            ['(select password from users)'],
            ['sum(gross_salary) + (select 1)'],
            ['abs(gross_salary)'],
            ['length(status)'],
            ['sum(gross_salary) || chr(65)'],
            ['sum(gross_salary`)'],
            ['sum(${gross_salary})'],
        ];
    }

    public function test_null_byte_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('sum(gross_salary)'.chr(0).'x');
    }

    public function test_unknown_field_rejected_at_compile(): void
    {
        $ast = $this->parser->parse('sum(unknown_col)');
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile($ast, $this->fieldMap, fn (ReportField $f) => $f->column);
    }

    public function test_permission_leak_when_field_absent_from_map(): void
    {
        // Kullanıcı maaş göremez → fieldMap'te gross_salary yok
        $map = [
            'status' => $this->fieldMap['status'],
            'net_salary' => $this->fieldMap['net_salary'],
        ];
        unset($map['gross_salary']);
        $ast = $this->parser->parse('sum(gross_salary)');
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile($ast, $map, fn (ReportField $f) => $f->column);
    }

    public function test_division_by_zero_uses_nullif(): void
    {
        $ast = $this->parser->parse('sum(gross_salary) / sum(net_salary)');
        $compiled = $this->compiler->compile($ast, $this->fieldMap, fn (ReportField $f) => $f->column);
        $this->assertStringContainsString('NULLIF', $compiled['sql']);
    }

    public function test_valid_expression_compiles(): void
    {
        $ast = $this->parser->parse('if(sum(gross_salary) > 0, sum(gross_salary) / count(*), 0)');
        $compiled = $this->compiler->compile($ast, $this->fieldMap, fn (ReportField $f) => $f->column);
        $this->assertStringContainsString('CASE WHEN', $compiled['sql']);
        $this->assertStringContainsString('SUM(employees.gross_salary)', $compiled['sql']);
    }

    public function test_count_star_allowed(): void
    {
        $ast = $this->parser->parse('count(*)');
        $compiled = $this->compiler->compile($ast, $this->fieldMap, fn (ReportField $f) => $f->column);
        $this->assertSame('COUNT(*)', $compiled['sql']);
    }

    public function test_bare_field_outside_agg_rejected(): void
    {
        $ast = $this->parser->parse('gross_salary + 1');
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile($ast, $this->fieldMap, fn (ReportField $f) => $f->column);
    }
}
