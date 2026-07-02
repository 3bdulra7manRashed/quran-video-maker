export function formatNumber(value: number, locale: string = 'ar'): string {
  try {
    return new Intl.NumberFormat(locale).format(value);
  } catch (e) {
    return String(value);
  }
}
