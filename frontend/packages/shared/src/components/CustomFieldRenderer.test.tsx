import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { CustomFieldValue } from '../types/modules';
import { CustomFieldRenderer, type CustomFieldDefinition } from './CustomFieldRenderer';

function field(overrides: Partial<CustomFieldDefinition>): CustomFieldDefinition {
  return { id: 1, field_key: 'alan', field_label: 'Alan', field_type: 'text', is_required: false, ...overrides };
}

function Controlled({ fields, onChange }: { fields: CustomFieldDefinition[]; onChange: (k: string, v: CustomFieldValue) => void }) {
  const [values, setValues] = useState<Record<string, CustomFieldValue>>({});
  return (
    <CustomFieldRenderer
      fields={fields}
      values={values}
      onChange={(key, value) => {
        setValues((prev) => ({ ...prev, [key]: value }));
        onChange(key, value);
      }}
    />
  );
}

describe('CustomFieldRenderer', () => {
  it('alan yoksa hiçbir şey render etmez', () => {
    const { container } = render(<CustomFieldRenderer fields={[]} values={{}} onChange={vi.fn()} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('etiket, zorunluluk işareti ve yardım metnini gösterir', () => {
    render(
      <CustomFieldRenderer
        fields={[field({ field_label: 'Kan Grubu', is_required: true, help_text: 'Acil durumlar için' })]}
        values={{}}
        onChange={vi.fn()}
      />
    );
    expect(screen.getByText('Kan Grubu')).toBeInTheDocument();
    expect(screen.getByText('*')).toBeInTheDocument();
    expect(screen.getByText('Acil durumlar için')).toBeInTheDocument();
  });

  it('metin alanına yazılan değeri field_key ile bildirir', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Controlled fields={[field({ field_key: 'lakap', placeholder: 'Lakap' })]} onChange={onChange} />);

    await user.type(screen.getByPlaceholderText('Lakap'), 'Ali');

    expect(onChange).toHaveBeenLastCalledWith('lakap', 'Ali');
  });

  it('varsayılan değeri ve tip bazlı input\'u kullanır', () => {
    const { container } = render(
      <CustomFieldRenderer
        fields={[
          field({ id: 1, field_key: 'yas', field_type: 'number', default_value: '30' }),
          field({ id: 2, field_key: 'giris', field_type: 'date' }),
          field({ id: 3, field_key: 'not', field_type: 'textarea' }),
        ]}
        values={{}}
        onChange={vi.fn()}
      />
    );
    expect(container.querySelector('input[type="number"]')).toHaveValue(30);
    expect(container.querySelector('input[type="date"]')).toBeInTheDocument();
    expect(container.querySelector('textarea')).toBeInTheDocument();
  });

  it('checkbox boolean, radio seçili değer bildirir', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <Controlled
        fields={[
          field({ id: 1, field_key: 'engelli', field_type: 'checkbox', placeholder: 'Engelli' }),
          field({ id: 2, field_key: 'vardiya', field_type: 'radio', field_options: [{ value: 'gunduz', label: 'Gündüz' }, { value: 'gece', label: 'Gece' }] }),
        ]}
        onChange={onChange}
      />
    );

    await user.click(screen.getByRole('checkbox'));
    // Etiketler input'a bağlı değil (label htmlFor yok); değerle seçilir.
    const night = screen.getAllByRole('radio').find((r) => (r as HTMLInputElement).value === 'gece');
    expect(night).toBeDefined();
    await user.click(night as HTMLElement);

    expect(onChange).toHaveBeenCalledWith('engelli', true);
    expect(onChange).toHaveBeenCalledWith('vardiya', 'gece');
    expect(night).toBeChecked();
    expect(screen.getByText('Gece')).toBeInTheDocument();
  });

  it('select tipi ortak Select bileşenini kullanır', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <Controlled
        fields={[field({ field_key: 'beden', field_label: 'Beden', field_type: 'select', field_options: [{ value: 'm', label: 'M' }, { value: 'l', label: 'L' }] })]}
        onChange={onChange}
      />
    );

    await user.click(screen.getByRole('combobox', { name: 'Beden' }));
    await user.click(await screen.findByRole('option', { name: 'L' }));

    expect(onChange).toHaveBeenCalledWith('beden', 'l');
  });

  it('readonly modda alanlar düzenlenemez', () => {
    render(<CustomFieldRenderer fields={[field({ placeholder: 'Salt okunur' })]} values={{ alan: 'x' }} onChange={vi.fn()} readonly />);
    expect(screen.getByPlaceholderText('Salt okunur')).toBeDisabled();
  });
});
