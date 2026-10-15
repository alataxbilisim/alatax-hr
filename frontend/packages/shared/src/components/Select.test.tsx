import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Select, SELECT_EMPTY_VALUE } from './Select';

const OPTIONS = [
  { value: 'active', label: 'Aktif', color: '#10b981' },
  { value: 'passive', label: 'Pasif' },
  { value: 'locked', label: 'Kilitli', disabled: true },
];

describe('Select (lookup)', () => {
  it('değer yokken placeholder gösterir', () => {
    render(<Select options={OPTIONS} placeholder="Durum seçin" aria-label="Durum" />);
    expect(screen.getByRole('combobox', { name: 'Durum' })).toHaveTextContent('Durum seçin');
  });

  it('seçili değerin etiketini gösterir', () => {
    render(<Select options={OPTIONS} value="passive" aria-label="Durum" />);
    expect(screen.getByRole('combobox', { name: 'Durum' })).toHaveTextContent('Pasif');
  });

  it('seçenek seçilince onChange value ile çağrılır', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Select options={OPTIONS} onChange={onChange} aria-label="Durum" />);

    await user.click(screen.getByRole('combobox', { name: 'Durum' }));
    await user.click(await screen.findByRole('option', { name: 'Aktif' }));

    expect(onChange).toHaveBeenCalledWith('active');
  });

  it('pasif seçenek seçilemez', async () => {
    const user = userEvent.setup();
    render(<Select options={OPTIONS} onChange={vi.fn()} aria-label="Durum" />);

    await user.click(screen.getByRole('combobox', { name: 'Durum' }));

    expect(await screen.findByRole('option', { name: 'Kilitli' })).toHaveAttribute('aria-disabled', 'true');
  });

  it('allowEmpty: boş seçenek sentinel ile render edilir ve onChange("") üretir', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Select options={OPTIONS} value="active" onChange={onChange} allowEmpty emptyLabel="Tümü" aria-label="Durum" />);

    await user.click(screen.getByRole('combobox', { name: 'Durum' }));
    await user.click(await screen.findByRole('option', { name: 'Tümü' }));

    expect(onChange).toHaveBeenCalledWith('');
    expect(onChange).not.toHaveBeenCalledWith(SELECT_EMPTY_VALUE);
  });

  it('boş string ve sentinel değerli seçenekleri listeden ayıklar', async () => {
    const user = userEvent.setup();
    render(
      <Select
        options={[{ value: '', label: 'Boş' }, { value: SELECT_EMPTY_VALUE, label: 'Sentinel' }, ...OPTIONS]}
        aria-label="Durum"
      />
    );

    await user.click(screen.getByRole('combobox', { name: 'Durum' }));

    expect(await screen.findAllByRole('option')).toHaveLength(OPTIONS.length);
  });

  it('clearable: temizle butonu seçimi kaldırır', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Select options={OPTIONS} value="active" onChange={onChange} clearable aria-label="Durum" />);

    await user.click(screen.getByRole('button', { name: 'Seçimi temizle' }));

    expect(onChange).toHaveBeenCalledWith('');
  });

  it('disabled iken açılmaz ve temizle butonu gösterilmez', () => {
    render(<Select options={OPTIONS} value="active" clearable disabled aria-label="Durum" />);
    expect(screen.getByRole('combobox', { name: 'Durum' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Seçimi temizle' })).not.toBeInTheDocument();
  });
});
