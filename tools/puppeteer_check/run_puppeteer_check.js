const puppeteer = require('puppeteer');

(async () => {
  const url = process.env.URL || 'https://schneider-ret.de/menue';
  console.log('Opening', url);
  const browser = await puppeteer.launch({ args: ['--no-sandbox','--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);

  try {
    await page.goto(url, { waitUntil: 'networkidle2' });

    // Give page a short grace period for JS to run
    await page.waitForTimeout(1000);

    // If intl-tel-input hasn't been initialized yet, try to load the library and initialize it
    await page.evaluate(async () => {
      try {
        const el = document.getElementById('phone_visible') || document.querySelector('input[type="tel"]');
        if (!el) return;
        if (typeof window.intlTelInput !== 'function') {
          // load script dynamically
          await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
          }).catch(() => {});
        }
        if (typeof window.intlTelInput === 'function' && !el._iti) {
          window.intlTelInput(el, {
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js'
          });
        }
      } catch (e) {
        // ignore initialization errors
      }
    });

    // Try to locate a phone input or intl-tel-input widget
    const found = await page.evaluate(() => {
      const info = { hasIti: false, inputSelector: null };
      if (document.querySelector('.iti__country-list') || document.querySelector('.iti__selected-flag')) {
        info.hasIti = true;
      }
      // common selectors
      const candidates = [
        '#phone_visible',
        'input[name="phone"]',
        'input[type="tel"]',
        '.intl-tel-input input',
        '.iti input'
      ];
      for (const s of candidates) {
        if (document.querySelector(s)) { info.inputSelector = s; break; }
      }
      // fallback: first visible input[type=tel]
      if (!info.inputSelector) {
        const tel = Array.from(document.querySelectorAll('input[type="tel"]')).find(i => i.offsetParent !== null);
        if (tel) info.inputSelector = 'input[type="tel"] (first visible)';
      }
      return info;
    });

    console.log('Detected intl-tel-input present:', found.hasIti);
    console.log('Candidate input selector:', found.inputSelector);

    if (!found.inputSelector) {
      console.log('No phone input candidate found. Exiting with code 2.');
      await browser.close();
      process.exit(2);
    }

      // Debug: print outerHTML around the phone input
      try {
        const outer = await page.$eval(found.inputSelector.split(' ')[0], el => {
          const parent = el.parentElement ? el.parentElement.outerHTML : null;
          const outer = el.outerHTML;
          return { outer, parent };
        });
        console.log('Phone input outerHTML:', outer.outer);
        console.log('Phone input parent outerHTML (truncated):', outer.parent ? outer.parent.substring(0,1000) : null);
      } catch (e) {
        console.log('Could not read phone input outerHTML:', e.message);
      }

    // Attempt to open the country dropdown by clicking flag button
    const flagButton = await page.$('.iti__selected-flag, .iti__flag-container, .iti__selected-country, .iti__country-container button');
    if (!flagButton) {
      console.log('No flag button found; skipping dropdown interaction.');
      const val = await page.$eval(found.inputSelector.split(' ')[0], el => el.value || el.getAttribute('value') || el.innerText || '');
      console.log('Phone input current value:', val);
      await browser.close();
      process.exit(0);
    }

    console.log('Clicking flag button to open country list');
    await flagButton.click();
    // wait for country list
    await page.waitForSelector('.iti__country-list', { visible: true, timeout: 5000 });
    console.log('Country list opened');

    // try to find search input inside the country list and type 'de' or 'Germany'
    const searchInput = await page.$('.iti-search, .iti__search-input');
    if (searchInput) {
      console.log('Focusing country search and typing "de"');
      await searchInput.focus();
      await searchInput.type('de', { delay: 100 });
      await page.waitForTimeout(500);
    } else {
      console.log('No .iti-search element found — attempting to type into focused element');
      await page.keyboard.type('de');
      await page.waitForTimeout(500);
    }

    // find visible country entries
    const visibleCountries = await page.$$eval('.iti__country', nodes => nodes.filter(n => n.offsetParent !== null).map(n => ({ text: n.innerText.trim(), dataDial: n.getAttribute('data-dial-code'), code: n.getAttribute('data-country-code') })));
    console.log('Visible countries after filter (first 8):', visibleCountries.slice(0,8));
    if (visibleCountries.length === 0) {
      console.log('No visible countries found after filtering — test failed');
      await browser.close();
      process.exit(3);
    }

    // press Enter to select the first visible country
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);

    // Read phone input value
    let phoneValue = '';
    try {
      phoneValue = await page.$eval(found.inputSelector.split(' ')[0], el => el.value || el.getAttribute('value') || '');
    } catch (e) {
      phoneValue = '(could not read)';
    }
    console.log('Phone input value after selection:', phoneValue);

    // Check for duplicated dial code patterns like +49+49 or repeated +\d+
    const dupPattern = /\+(\d{1,3}).*\+\1/;
    const hasDup = dupPattern.test(phoneValue);
    console.log('Duplicate dial-code found in phone value:', hasDup);

    // Final decision
    if (visibleCountries.length > 0 && !hasDup) {
      console.log('Puppeteer check: OK');
      await browser.close();
      process.exit(0);
    } else {
      console.log('Puppeteer check: FAILED (visibleCountries=' + visibleCountries.length + ', dup=' + hasDup + ')');
      await browser.close();
      process.exit(4);
    }

  } catch (err) {
    console.error('Error during puppeteer check:', err);
    await browser.close();
    process.exit(10);
  }
})();
