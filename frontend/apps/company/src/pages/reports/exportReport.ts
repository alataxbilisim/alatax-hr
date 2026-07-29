import ExcelJS from 'exceljs';
import { jsPDF } from 'jspdf';
import {
  reportsApi,
  type ReportQueryPayload,
  type ReportRunResult,
} from '@shared/services/api';
import { cellToExportValue, isReportRunResult } from './reportHelpers';

const EXPORT_WARN_LIMIT = 50000;

export interface ExportOutcome {
  truncated: boolean;
  rowCount: number;
  exportMax: number;
}

async function fetchExportRows(payload: ReportQueryPayload): Promise<ReportRunResult> {
  const res = await reportsApi.export({
    ...payload,
    limit: EXPORT_WARN_LIMIT,
  });
  const data: unknown = res.data.data;
  if (!isReportRunResult(data)) {
    throw new Error('invalid_export');
  }
  return data;
}

export async function exportReportExcel(
  payload: ReportQueryPayload,
  fileName: string
): Promise<ExportOutcome> {
  const data = await fetchExportRows(payload);
  const fields = data.meta.fields;
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet('Rapor');
  sheet.addRow(fields);
  for (const row of data.rows) {
    sheet.addRow(fields.map((f) => cellToExportValue(row[f] ?? null)));
  }
  const note = data.meta.export_note;
  if (note) {
    const noteSheet = workbook.addWorksheet('Notlar');
    noteSheet.addRow(['Gizlilik notu']);
    noteSheet.addRow([note]);
    if (data.meta.hidden_fields && data.meta.hidden_fields.length > 0) {
      noteSheet.addRow(['Gizlenen alanlar']);
      for (const h of data.meta.hidden_fields) {
        noteSheet.addRow([h.label, h.reason]);
      }
    }
  }
  const buffer = await workbook.xlsx.writeBuffer();
  const bytes = new Uint8Array(buffer);
  const blob = new Blob([bytes], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });
  downloadBlob(blob, `${fileName}.xlsx`);
  return {
    truncated: Boolean(data.meta.truncated),
    rowCount: data.meta.count,
    exportMax: data.meta.export_max ?? EXPORT_WARN_LIMIT,
  };
}

export async function exportReportPdf(
  payload: ReportQueryPayload,
  fileName: string,
  title: string
): Promise<ExportOutcome> {
  const data = await fetchExportRows(payload);
  const fields = data.meta.fields;
  const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
  doc.setFontSize(12);
  doc.text(title, 40, 36);
  doc.setFontSize(8);

  const startY = 56;
  const colW = Math.max(60, Math.floor((doc.internal.pageSize.getWidth() - 80) / Math.max(fields.length, 1)));
  let y = startY;

  fields.forEach((f, i) => {
    doc.text(String(f).slice(0, 18), 40 + i * colW, y);
  });
  y += 14;
  doc.setDrawColor(180);
  doc.line(40, y - 8, 40 + fields.length * colW, y - 8);

  const maxRows = Math.min(data.rows.length, 200);
  for (let r = 0; r < maxRows; r += 1) {
    const row = data.rows[r];
    if (!row) continue;
    if (y > doc.internal.pageSize.getHeight() - 40) {
      doc.addPage();
      y = 40;
    }
    fields.forEach((f, i) => {
      const cell = cellToExportValue(row[f] ?? null);
      doc.text(String(cell).slice(0, 18), 40 + i * colW, y);
    });
    y += 12;
  }

  if (data.rows.length > maxRows) {
    doc.text(`… +${data.rows.length - maxRows}`, 40, y + 8);
  }

  if (data.meta.export_note) {
    const pageH = doc.internal.pageSize.getHeight();
    doc.setFontSize(7);
    doc.text(data.meta.export_note.slice(0, 120), 40, pageH - 24);
  }

  doc.save(`${fileName}.pdf`);
  return {
    truncated: Boolean(data.meta.truncated),
    rowCount: data.meta.count,
    exportMax: data.meta.export_max ?? EXPORT_WARN_LIMIT,
  };
}

function downloadBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  a.click();
  URL.revokeObjectURL(url);
}
