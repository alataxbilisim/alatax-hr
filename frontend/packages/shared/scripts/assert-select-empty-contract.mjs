/**
 * Radix Select boş-değer sözleşmesi — derleme değil mantık doğrulaması.
 * Select.tsx ile birebir: boş string Item YASAK; sentinel dışarıda ''.
 *
 * Çalıştır: node frontend/packages/shared/scripts/assert-select-empty-contract.mjs
 */
const SELECT_EMPTY_VALUE = '__ax_empty__';

function sanitizeOptions(options) {
  return options.filter((opt) => opt.value !== '' && opt.value !== SELECT_EMPTY_VALUE);
}

function toRootValue(value, allowEmpty) {
  const isEmpty = value === undefined || value === '';
  if (isEmpty) {
    return allowEmpty ? SELECT_EMPTY_VALUE : undefined;
  }
  return value;
}

function fromRadixValue(v) {
  if (v === SELECT_EMPTY_VALUE) {
    return '';
  }
  return v;
}

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exitCode = 1;
  } else {
    console.log('OK:', msg);
  }
}

// 1) Boş option filtrelenir (Radix value="" tuzağı)
const sanitized = sanitizeOptions([
  { value: '', label: 'Tümü' },
  { value: SELECT_EMPTY_VALUE, label: 'bad' },
  { value: 'active', label: 'Aktif' },
]);
assert(sanitized.length === 1 && sanitized[0].value === 'active', 'boş/sentinel option sanitize');

// 2) allowEmpty + boş value → sentinel (ilk gerçek öğe seçilmez)
assert(toRootValue('', true) === SELECT_EMPTY_VALUE, 'allowEmpty boş → sentinel');
assert(toRootValue(undefined, true) === SELECT_EMPTY_VALUE, 'allowEmpty undefined → sentinel');
assert(toRootValue('', false) === undefined, 'zorunlu boş → undefined (ilk öğe değil)');
assert(toRootValue('active', true) === 'active', 'dolu value korunur');

// 3) onChange: sentinel → ''
assert(fromRadixValue(SELECT_EMPTY_VALUE) === '', 'sentinel onChange → boş string');
assert(fromRadixValue('active') === 'active', 'normal onChange');

// 4) Filtre "Tümü" senaryosu: submit payload boş kalır
const filterSubmit = fromRadixValue(toRootValue('', true));
assert(filterSubmit === '', 'filtre Tümü submit → boş (ilk öğe değil)');

if (process.exitCode) {
  console.error('\nSelect empty contract FAILED');
  process.exit(1);
}
console.log('\nSelect empty contract PASSED');
