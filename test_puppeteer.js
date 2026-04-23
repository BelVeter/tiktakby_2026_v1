const puppeteer = require('puppeteer');
(async () => {
  const browser = await puppeteer.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  
  // Set cookies to bypass login
  await page.setCookie({ name: 'tt_is_logged_in', value: '1', domain: 'localhost' });
  // We need to actually login to get a session
  await page.goto('http://localhost/bb/one_login.php', { waitUntil: 'networkidle2' });
  await page.select('select[name="of_select"]', '1');
  await page.type('input[name="login"]', '123');
  await page.type('input[name="pass"]', '123');
  await page.click('input[type="submit"]');
  await page.waitForNavigation();
  
  await page.goto('http://localhost/bb/rent_zayavk.php', { waitUntil: 'networkidle2' });
  
  page.on('console', msg => console.log('CONSOLE:', msg.text()));
  page.on('dialog', async dialog => {
    console.log('DIALOG:', dialog.message());
    await dialog.accept();
  });
  
  page.on('request', request => {
    if (request.method() === 'POST') {
      console.log('POST URL:', request.url());
      console.log('POST DATA:', request.postData());
    }
  });

  const buttons = await page.$$('.z_btn_del');
  if (buttons.length > 0) {
    console.log('Clicking delete button...');
    await Promise.all([
      page.waitForNavigation(),
      buttons[0].click()
    ]);
    console.log('Page reloaded. Success.');
  } else {
    console.log('No delete buttons found.');
  }
  
  await browser.close();
})();
