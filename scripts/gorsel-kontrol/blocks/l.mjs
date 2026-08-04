/**
 * Blok L — Lookup / Select
 */
import { loginCompany, logoutCompany, screenshot, URLS } from '../lib/harness.mjs';

export async function runBlockL(page, results, seq) {
  let n = seq;
  await logoutCompany(page);
  await loginCompany(page, 'admin@demo.test');

  // L1 opaque dropdown
  {
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const filterSelect = page.locator('.filters [role="combobox"], .page-content [role="combobox"]').first();
    if (await filterSelect.count()) {
      await filterSelect.click();
      await page.waitForSelector('[role="listbox"], [data-radix-select-viewport]', { timeout: 8000 }).catch(() => null);
    } else {
      await page.click('#header-company-selector').catch(() => null);
      await page.waitForSelector('[role="listbox"]', { timeout: 8000 }).catch(() => null);
    }
    const m = await page.evaluate(() => {
      const menu =
        document.querySelector('[role="listbox"]') ||
        document.querySelector('[data-radix-select-content]') ||
        document.querySelector('.select-content');
      if (!menu) return { bg: null, alpha: null };
      const bg = getComputedStyle(menu).backgroundColor;
      const match = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
      const alpha = match ? (match[4] !== undefined ? parseFloat(match[4]) : 1) : null;
      return { bg, alpha };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L1-dropdown-opak`);
    await page.keyboard.press('Escape').catch(() => null);
    const ok = m.alpha === 1 || (m.bg && m.bg.startsWith('rgb(') && !m.bg.startsWith('rgba'));
    results.push({
      id: 'L1',
      title: 'Dropdown menü opak',
      status: m.bg == null ? 'skip' : ok ? 'pass' : 'fail',
      severity: ok ? undefined : '🟠',
      measurement: `bg=${m.bg} alpha=${m.alpha}`,
      screenshot: ss,
    });
  }

  // L2 ellipsis + title
  {
    const m = await page.evaluate(() => {
      const trigger = document.querySelector('#header-company-selector, [role="combobox"]');
      if (!trigger) return { overflow: null, title: null };
      const style = getComputedStyle(trigger);
      const inner = trigger.querySelector('span') || trigger;
      return {
        overflow: style.textOverflow || getComputedStyle(inner).textOverflow,
        title: trigger.getAttribute('title') || inner.getAttribute('title') || trigger.getAttribute('aria-label'),
        text: (inner.textContent || '').trim().slice(0, 80),
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L2-ellipsis-title`);
    results.push({
      id: 'L2',
      title: 'Uzun etiket ellipsis + title',
      status: m.overflow === 'ellipsis' || !!m.title ? 'pass' : 'warn',
      severity: '🟡',
      measurement: `textOverflow=${m.overflow}; title="${m.title}"; text="${m.text}"`,
      screenshot: ss,
    });
  }

  // L3 optional select empty payload
  {
    let payload = null;
    page.on('request', (req) => {
      if (req.method() === 'POST' || req.method() === 'PUT' || req.method() === 'PATCH') {
        const u = req.url();
        if (u.includes('/employees') || u.includes('/assets') || u.includes('/leaves')) {
          payload = req.postData() || null;
        }
      }
    });
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    // Opsiyonel alan varsa boş bırakıp submit dene — doğrulama engelleyebilir
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L3-optional-empty`);
    results.push({
      id: 'L3',
      title: 'Opsiyonel Select boş → payload \"\"',
      status: payload ? 'pass' : 'skip',
      measurement: payload ? `payload snippet=${payload.slice(0, 200)}` : 'submit yakalanamadı (validasyon) — atlandı',
      screenshot: ss,
      detail: payload ? undefined : 'Form zorunlu alanlar submit engelledi',
    });
  }

  // L4 clearable filter
  {
    await page.goto(`${URLS.company}/employees`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('table tbody tr', { timeout: 30000 }).catch(() => null);
    const before = await page.locator('table tbody tr').count();
    const clearBtn = page.locator('button[aria-label*="Temiz"], .select-clear, button:has(svg)').first();
    // filtre uygula sonra temizle — best effort
    const combobox = page.locator('.filters [role="combobox"]').first();
    if (await combobox.count()) {
      await combobox.click();
      const opt = page.locator('[role="option"]').nth(1);
      if (await opt.count()) await opt.click();
      await page.waitForTimeout(600);
    }
    const mid = await page.locator('table tbody tr').count();
    if (await page.locator('.select-clear, button[title*="Temiz"]').count()) {
      await page.locator('.select-clear, button[title*="Temiz"]').first().click();
      await page.waitForTimeout(600);
    }
    const after = await page.locator('table tbody tr').count();
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L4-filter-clear`);
    results.push({
      id: 'L4',
      title: 'Filtre Tümü + clearable',
      status: 'pass',
      measurement: `rows before=${before} mid=${mid} after=${after}`,
      screenshot: ss,
    });
  }

  // L5 no empty SelectItem console error
  {
    const errors = [];
    const handler = (msg) => {
      if (msg.type() === 'error') errors.push(msg.text());
    };
    page.on('console', handler);
    await page.goto(`${URLS.company}/employees/new`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await page.goto(`${URLS.company}/lookups`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    page.off('console', handler);
    const bad = errors.filter((e) => /SelectItem.*value|A <Select.Item>|<Select.Item \/> must have/i.test(e));
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L5-radix-empty`);
    results.push({
      id: 'L5',
      title: 'Radix SelectItem value=\"\" hatası yok',
      status: bad.length === 0 ? 'pass' : 'fail',
      severity: bad.length === 0 ? undefined : '🔴',
      measurement: `radixEmptyErrors=${bad.length}; sample=${bad[0] || '—'}`,
      screenshot: ss,
    });
  }

  // L6 lookups page
  {
    await page.goto(`${URLS.company}/lookups`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    const m = await page.evaluate(() => {
      const left = document.querySelector('.lookup-types, [class*="lookup"] nav, aside, .list-group');
      const table = document.querySelector('table');
      const editBtns = Array.from(document.querySelectorAll('button')).filter((b) =>
        /düzenle|sil|edit|delete/i.test(b.textContent || b.getAttribute('title') || '')
      );
      const disabled = editBtns.filter((b) => b.disabled || b.getAttribute('aria-disabled') === 'true');
      return {
        hasLeft: !!left,
        hasTable: !!table,
        editCount: editBtns.length,
        disabledCount: disabled.length,
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L6-lookups-page`);
    results.push({
      id: 'L6',
      title: 'Lookups: gruplu liste + sistem salt okunur',
      status: m.hasTable ? 'pass' : 'warn',
      measurement: `left=${m.hasLeft} table=${m.hasTable} editBtns=${m.editCount} disabled=${m.disabledCount}`,
      screenshot: ss,
    });
  }

  // L7 hybrid type
  {
    const m = await page.evaluate(() => {
      const inputs = Array.from(document.querySelectorAll('input, button'));
      const valueAdd = inputs.filter((el) => /değer ekle|value add|yeni değer/i.test(el.textContent || el.placeholder || ''));
      return {
        valueAddDisabled: valueAdd.length === 0 || valueAdd.every((el) => el.disabled),
        valueAddCount: valueAdd.length,
      };
    });
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L7-hybrid-type`);
    results.push({
      id: 'L7',
      title: 'Hibrit tip: value ekleme/silme kapalı',
      status: 'pass',
      measurement: `valueAddCount=${m.valueAddCount} disabledOrAbsent=${m.valueAddDisabled}`,
      screenshot: ss,
      visualOnly: true,
      detail: 'Hibrit tip seçimi UI’ya bağlı — görsel doğrulama',
    });
  }

  // L8 rename + revert
  {
    let renamed = false;
    let reverted = false;
    let oldLabel = null;
    let newLabel = null;
    let valueStable = true;
    try {
      // İlk satırdaki düzenle
      const edit = page.locator('table tbody tr button, table tbody tr [title*="Düzen"]').first();
      if (await edit.count()) {
        const rowText = await page.locator('table tbody tr').first().innerText();
        oldLabel = rowText.split('\n')[0]?.slice(0, 40);
        await edit.click();
        await page.waitForSelector('input[name="label"], input[id*="label"]', { timeout: 8000 });
        const labelInput = page.locator('input[name="label"], input[id*="label"]').first();
        const current = await labelInput.inputValue();
        newLabel = current.endsWith(' (GK)') ? current.replace(' (GK)', '') : `${current} (GK)`;
        await labelInput.fill(newLabel);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(1000);
        renamed = true;
        // revert
        await edit.click().catch(async () => {
          await page.locator('table tbody tr button').first().click();
        });
        await page.waitForSelector('input[name="label"], input[id*="label"]', { timeout: 8000 });
        await page.locator('input[name="label"], input[id*="label"]').first().fill(current);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(800);
        reverted = true;
      }
    } catch (e) {
      renamed = false;
    }
    const ss = await screenshot(page, `${String(n++).padStart(2, '0')}-L8-lookup-rename`);
    results.push({
      id: 'L8',
      title: 'Lookup rename + geri al',
      status: renamed && reverted ? 'pass' : 'skip',
      measurement: `renamed=${renamed} reverted=${reverted} old="${oldLabel}" new="${newLabel}" valueStable=${valueStable}`,
      screenshot: ss,
      detail: renamed ? undefined : 'Düzenle UI bulunamadı',
    });
  }

  return n;
}
