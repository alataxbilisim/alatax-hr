import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import DataTable, { type Column } from './DataTable';

interface Row {
  id: number;
  name: string;
  department: string;
}

const columns: Column<Row>[] = [
  { key: 'name', title: 'Ad Soyad' },
  { key: 'department', title: 'Departman', render: (row) => <strong>{row.department.toUpperCase()}</strong> },
];

const rows: Row[] = [
  { id: 1, name: 'Ayşe Demir', department: 'ik' },
  { id: 2, name: 'Mehmet Kaya', department: 'muhasebe' },
];

describe('DataTable', () => {
  it('sütun başlıklarını ve satırları (özel render dahil) gösterir', () => {
    render(<DataTable columns={columns} data={rows} />);

    expect(screen.getByRole('columnheader', { name: 'Ad Soyad' })).toBeInTheDocument();
    const bodyRows = screen.getAllByRole('row').slice(1);
    expect(bodyRows).toHaveLength(2);
    expect(within(bodyRows[0]).getByText('Ayşe Demir')).toBeInTheDocument();
    expect(within(bodyRows[1]).getByText('MUHASEBE')).toBeInTheDocument();
  });

  it('boş durumda mesaj ve aksiyon gösterir', () => {
    render(
      <DataTable
        columns={columns}
        data={[]}
        emptyMessage="Henüz personel yok"
        emptyAction={<button type="button">Personel ekle</button>}
      />
    );

    expect(screen.getByText('Henüz personel yok')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Personel ekle' })).toBeInTheDocument();
  });

  it('yüklenirken iskelet satırları gösterir, veri satırı göstermez', () => {
    const { container } = render(<DataTable columns={columns} data={rows} loading />);

    expect(container.querySelectorAll('.skeleton')).toHaveLength(5 * columns.length);
    expect(screen.queryByText('Ayşe Demir')).not.toBeInTheDocument();
  });

  it('tek sayfada sayfalama göstermez', () => {
    render(<DataTable columns={columns} data={rows} totalPages={1} onPageChange={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Sonraki sayfa' })).not.toBeInTheDocument();
  });

  it('sayfalama: toplam kaydı gösterir, sayfa değişimini bildirir, uçlarda butonları kilitler', async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(<DataTable columns={columns} data={rows} currentPage={1} totalPages={8} total={150} onPageChange={onPageChange} />);

    expect(screen.getByText('Toplam 150 kayıt')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Önceki sayfa' })).toBeDisabled();
    expect(screen.getByRole('button', { name: '5' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '6' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Sonraki sayfa' }));
    await user.click(screen.getByRole('button', { name: '3' }));

    expect(onPageChange).toHaveBeenNthCalledWith(1, 2);
    expect(onPageChange).toHaveBeenNthCalledWith(2, 3);
  });

  it('sayfalama penceresi aktif sayfayı ortalar, son sayfada sonraki butonu kilitlenir', () => {
    const { rerender } = render(<DataTable columns={columns} data={rows} currentPage={6} totalPages={10} onPageChange={vi.fn()} />);
    expect(screen.getAllByRole('button').map((b) => b.textContent).filter(Boolean)).toEqual(['4', '5', '6', '7', '8']);

    rerender(<DataTable columns={columns} data={rows} currentPage={10} totalPages={10} onPageChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Sonraki sayfa' })).toBeDisabled();
    expect(screen.getByText('Sayfa 10 / 10')).toBeInTheDocument();
  });

  it('arama kutusu değişimi bildirir', async () => {
    const user = userEvent.setup();
    const onSearchChange = vi.fn();
    render(<DataTable columns={columns} data={rows} onSearchChange={onSearchChange} searchPlaceholder="Personel ara" />);

    await user.type(screen.getByPlaceholderText('Personel ara'), 'a');

    expect(onSearchChange).toHaveBeenCalledWith('a');
  });

  it('seçili satırı vurgular ve özel satır anahtarı kullanır', () => {
    render(<DataTable columns={columns} data={rows} isRowSelected={(row) => row.id === 2} getRowKey={(row) => `r-${row.id}`} />);
    const selected = screen.getByText('Mehmet Kaya').closest('tr');
    expect(selected?.getAttribute('style')).toContain('var(--primary-soft)');
  });
});
