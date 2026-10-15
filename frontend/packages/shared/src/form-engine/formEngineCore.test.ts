import { describe, expect, it } from 'vitest';
import { buildSubmitPayload } from './buildSubmitPayload';
import { buildZodSchema, getVisibleFields, isFieldReadonly, isFieldVisible } from './buildZodSchema';
import { isValidTurkishNationalId } from './tckn';
import { makeFormField as makeField } from '../test';

const MESSAGES = { required: 'Zorunlu', email: 'E-posta geçersiz', tckn: 'TCKN geçersiz', number: 'Sayı girin' };

describe('görünürlük ve salt okunurluk', () => {
  it('pasif, gizli veya izinle gizlenmiş alan görünmez', () => {
    expect(isFieldVisible(makeField({}))).toBe(true);
    expect(isFieldVisible(makeField({ is_active: false }))).toBe(false);
    expect(isFieldVisible(makeField({ is_hidden: true }))).toBe(false);
    expect(isFieldVisible(makeField({ field_permission: 'hidden' }))).toBe(false);
    expect(isFieldReadonly(makeField({ field_permission: 'readonly' }))).toBe(true);
  });

  it('görünür alanları sort_order ile sıralar', () => {
    const fields = [
      makeField({ id: 1, field_key: 'b', sort_order: 2 }),
      makeField({ id: 2, field_key: 'gizli', sort_order: 0, is_hidden: true }),
      makeField({ id: 3, field_key: 'a', sort_order: 1 }),
    ];
    expect(getVisibleFields(fields).map((f) => f.field_key)).toEqual(['a', 'b']);
  });
});

describe('buildZodSchema', () => {
  it('zorunlu metin alanı boş geçilemez, opsiyonel alan geçilir', () => {
    const schema = buildZodSchema(
      [makeField({ field_key: 'ad', effective_required: true }), makeField({ id: 2, field_key: 'lakap' })],
      MESSAGES
    );
    const result = schema.safeParse({ ad: '  ', lakap: '' });
    expect(result.success).toBe(false);
    expect(result.error?.issues[0]?.message).toBe('Zorunlu');
    expect(schema.safeParse({ ad: 'Ayşe', lakap: '' }).success).toBe(true);
  });

  it('salt okunur zorunlu alan doğrulanmaz', () => {
    const schema = buildZodSchema([makeField({ field_key: 'sicil', effective_required: true, field_permission: 'readonly' })], MESSAGES);
    expect(schema.safeParse({ sicil: '' }).success).toBe(true);
  });

  it('gizli alan şemaya girmez', () => {
    const schema = buildZodSchema([makeField({ field_key: 'maas', effective_required: true, field_permission: 'hidden' })], MESSAGES);
    expect(Object.keys(schema.shape)).toEqual([]);
  });

  it('e-posta biçimini doğrular', () => {
    const schema = buildZodSchema([makeField({ field_key: 'eposta', field_type: 'email' })], MESSAGES);
    expect(schema.safeParse({ eposta: '' }).success).toBe(true);
    const bad = schema.safeParse({ eposta: 'gecersiz' });
    expect(bad.success).toBe(false);
    expect(bad.error?.issues[0]?.message).toBe('E-posta geçersiz');
  });

  it('TCKN algoritmasını uygular', () => {
    const schema = buildZodSchema([makeField({ field_key: 'tckn', field_type: 'tckn', effective_required: true })], MESSAGES);
    expect(schema.safeParse({ tckn: '10000000146' }).success).toBe(true);
    expect(schema.safeParse({ tckn: '12345678901' }).success).toBe(false);
    expect(schema.safeParse({ tckn: '' }).success).toBe(false);
  });

  it('zorunlu çoklu seçim en az bir değer ister', () => {
    const schema = buildZodSchema([makeField({ field_key: 'diller', field_type: 'multiselect', effective_required: true })], MESSAGES);
    expect(schema.safeParse({ diller: [] }).success).toBe(false);
    expect(schema.safeParse({ diller: ['tr'] }).success).toBe(true);
  });
});

describe('isValidTurkishNationalId', () => {
  it('geçerli ve geçersiz numaraları ayırır', () => {
    expect(isValidTurkishNationalId('10000000146')).toBe(true);
    expect(isValidTurkishNationalId('00000000000')).toBe(false);
    expect(isValidTurkishNationalId('1234')).toBe(false);
  });
});

describe('buildSubmitPayload', () => {
  const fields = [
    makeField({ id: 1, field_key: 'first_name', is_system: true }),
    makeField({ id: 2, field_key: 'salary', is_system: true, field_type: 'decimal' }),
    makeField({ id: 3, field_key: 'tisort_bedeni', is_system: false }),
    makeField({ id: 4, field_key: 'engelli', is_system: false, field_type: 'checkbox' }),
    makeField({ id: 5, field_key: 'sicil_no', is_system: true, field_permission: 'readonly' }),
    makeField({ id: 6, field_key: 'gizli', is_system: false, field_permission: 'hidden' }),
  ];

  it('sistem ve özel alanları ayırır, salt okunur ve gizli alanları göndermez', () => {
    const payload = buildSubmitPayload(
      { first_name: 'Ayşe', salary: '45000.50', tisort_bedeni: 'M', engelli: 'true', sicil_no: 'X', gizli: 'Y' },
      fields
    );
    expect(payload).toEqual({
      system: { first_name: 'Ayşe', salary: 45000.5 },
      custom_fields: { tisort_bedeni: 'M', engelli: true },
    });
  });

  it('boş sayıyı null yapar ve formda olmayan alanı atlar', () => {
    const payload = buildSubmitPayload({ salary: '' }, fields);
    expect(payload).toEqual({ system: { salary: null }, custom_fields: {} });
  });
});
