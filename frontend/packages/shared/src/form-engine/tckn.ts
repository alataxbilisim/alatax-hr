/**
 * TCKN algoritmik doğrulama — backend TurkishNationalId ile senkron.
 */
export function isValidTurkishNationalId(value: string): boolean {
  if (!/^[1-9][0-9]{10}$/.test(value)) {
    return false;
  }

  const digits = value.split('').map((c) => Number(c));
  const oddSum = digits[0] + digits[2] + digits[4] + digits[6] + digits[8];
  const evenSum = digits[1] + digits[3] + digits[5] + digits[7];
  let digit10 = (oddSum * 7 - evenSum) % 10;
  if (digit10 < 0) {
    digit10 += 10;
  }
  if (digit10 !== digits[9]) {
    return false;
  }

  const digit11 = digits.slice(0, 10).reduce((a, b) => a + b, 0) % 10;
  return digit11 === digits[10];
}
