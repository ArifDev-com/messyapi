// electron-app/main.js
const { app, BrowserWindow } = require('electron');
const puppeteer = require('puppeteer-core');
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const unzipper = require('unzipper');

async function createWhatsAppWindow() {
  const win = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true
    }
  });

  try {
    // Download session data from server
    const sessionDir = path.join(app.getPath('userData'), 'whatsapp-session');
    await downloadAndExtractSession(sessionDir);

    // Launch local Puppeteer with the transferred session
    const browser = await puppeteer.launch({
      headless: false,
      executablePath: getChromePath(),
      userDataDir: sessionDir, // This is the key!
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        `--window-size=1200,800`
      ]
    });

    // Navigate to WhatsApp Web
    const pages = await browser.pages();
    const page = pages[0] || await browser.newPage();
    await page.goto('https://web.whatsapp.com', {
      waitUntil: 'networkidle2',
      timeout: 60000
    });

    // Get the HTML content and display it in Electron
    const content = await page.content();
    win.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(content)}`);

    // OR: Better - embed the browser view
    // This requires additional setup (see below)

  } catch (error) {
    console.error('Failed to initialize WhatsApp:', error);
    win.loadURL(`data:text/html,
      <html>
        <body style="font-family: sans-serif; padding: 20px;">
          <h1>Connection Error</h1>
          <p>${error.message}</p>
          <p>Make sure the sidecar server is running and you're authenticated.</p>
        </body>
      </html>
    `);
  }
}

async function downloadAndExtractSession(targetDir) {
  // Create directory if it doesn't exist
  if (!fs.existsSync(targetDir)) {
    fs.mkdirSync(targetDir, { recursive: true });
  }

  // Download session from server
  const response = await axios({
    method: 'get',
    url: 'http://127.0.0.1:3000/sessions/main/download',
    headers: { Authorization: 'Bearer YOUR_TOKEN' },
    responseType: 'stream'
  });

  // Extract to target directory
  await new Promise((resolve, reject) => {
    response.data
      .pipe(unzipper.Extract({ path: targetDir }))
      .on('close', resolve)
      .on('error', reject);
  });

  console.log('Session extracted to:', targetDir);
}

function getChromePath() {
  if (process.platform === 'darwin') {
    return '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  }
  // Add Windows and Linux paths as needed
  return null;
}

app.whenReady().then(createWhatsAppWindow);
