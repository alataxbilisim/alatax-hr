import { recruitmentApi } from '@shared/services/api';

function fileNameFromDisposition(disposition: unknown): string | null {
  if (typeof disposition !== 'string') return null;
  const encoded = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (encoded) return decodeURIComponent(encoded[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain ? plain[1] : null;
}

/**
 * CV'ler private diskte; yalnız yetki kontrollü uçtan blob olarak indirilir.
 */
export async function downloadApplicationCv(applicationId: number): Promise<void> {
  const response = await recruitmentApi.applications.downloadCv(applicationId);
  const fileName =
    fileNameFromDisposition(response.headers['content-disposition']) ?? `cv-${applicationId}`;
  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = window.document.createElement('a');
  link.href = url;
  link.download = fileName;
  window.document.body.appendChild(link);
  link.click();
  window.document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}
