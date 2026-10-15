/**
 * Tam sayfa yönlendirme (router dışı). Testlerde `vi.spyOn(browserNavigation, 'assign')` ile izlenir;
 * jsdom gerçek sayfa geçişi yapamaz.
 */
export const browserNavigation = {
  assign(url: string): void {
    window.location.href = url;
  },
};
