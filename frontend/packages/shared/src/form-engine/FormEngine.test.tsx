import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { makeFormDefinition, makeFormField } from '../test';
import { FormEngine } from './FormEngine';

const fields = [
  makeFormField({ id: 1, field_key: 'first_name', field_label: 'Ad', effective_required: true, sort_order: 1, placeholder: 'Ad' }),
  makeFormField({ id: 2, field_key: 'email', field_label: 'E-posta', field_type: 'email', sort_order: 2, placeholder: 'E-posta' }),
  makeFormField({ id: 3, field_key: 'tisort', field_label: 'Tişört', is_system: false, sort_order: 3, placeholder: 'Tişört' }),
  makeFormField({ id: 4, field_key: 'maas', field_label: 'Maaş', field_permission: 'hidden', sort_order: 4 }),
];

describe('FormEngine', () => {
  it('layout yoksa görünür alanları tek bölümde, gizli alanı göstermeden render eder', () => {
    render(<FormEngine definition={makeFormDefinition(fields)} onSubmit={vi.fn()} />);

    expect(screen.getByText('Personel Formu')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Ad')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Tişört')).toBeInTheDocument();
    expect(screen.queryByText('Maaş')).not.toBeInTheDocument();
  });

  it('layout bölümlerini sort_order ile render eder', () => {
    const definition = makeFormDefinition(fields, {
      layout: {
        sections: [
          { id: 'ek', label: 'Ek Bilgiler', sort_order: 2, rows: [{ sort_order: 0, fields: [{ field_key: 'tisort' }] }] },
          { id: 'kimlik', label: 'Kimlik', sort_order: 1, rows: [{ sort_order: 0, fields: [{ field_key: 'first_name' }] }] },
        ],
      },
    });
    render(<FormEngine definition={definition} onSubmit={vi.fn()} />);

    const headings = screen.getAllByRole('heading').map((h) => h.textContent);
    expect(headings).toEqual(['Kimlik', 'Ek Bilgiler']);
    expect(screen.queryByPlaceholderText('E-posta')).not.toBeInTheDocument();
  });

  it('zorunlu alan boşken gönderimi engeller ve mesaj gösterir', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<FormEngine definition={makeFormDefinition(fields)} onSubmit={onSubmit} messages={{ required: 'Bu alan zorunlu' }} />);

    await user.click(screen.getByRole('button', { name: 'Kaydet' }));

    expect(await screen.findByText('Bu alan zorunlu')).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('geçerli gönderimde sistem/özel alan ayrımlı payload üretir', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<FormEngine definition={makeFormDefinition(fields)} onSubmit={onSubmit} />);

    await user.type(screen.getByPlaceholderText('Ad'), 'Ayşe');
    await user.type(screen.getByPlaceholderText('Tişört'), 'M');
    await user.click(screen.getByRole('button', { name: 'Kaydet' }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit.mock.calls[0][0]).toEqual({
      system: { first_name: 'Ayşe', email: '' },
      custom_fields: { tisort: 'M' },
    });
  });

  it('varsayılan değerleri forma yükler ve iptal butonunu çalıştırır', async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();
    render(
      <FormEngine
        definition={makeFormDefinition(fields)}
        defaultValues={{ first_name: 'Mehmet' }}
        onSubmit={vi.fn()}
        onCancel={onCancel}
        cancelLabel="Vazgeç"
      />
    );

    expect(screen.getByPlaceholderText('Ad')).toHaveValue('Mehmet');
    await user.click(screen.getByRole('button', { name: 'Vazgeç' }));
    expect(onCancel).toHaveBeenCalled();
  });

  it('loading iken gönder butonu pasif ve kaydediliyor etiketi gösterir', () => {
    render(<FormEngine definition={makeFormDefinition(fields)} onSubmit={vi.fn()} loading savingLabel="Kaydediliyor" />);
    expect(screen.getByRole('button', { name: 'Kaydediliyor' })).toBeDisabled();
  });
});
