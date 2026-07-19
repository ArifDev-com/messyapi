const { app } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const axios = require('axios');
const unzipper = require('unzipper');
const fs = require('fs');

const SERVER_URL = 'http://127.0.0.1:3000';
const TOKEN = '';

function getChromePath() {
  switch (process.platform) {
    case 'darwin':
      return '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    case 'win32':
      return 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    case 'linux':
      return '/usr/bin/google-chrome';
    default:
      throw new Error('Unsupported platform');
  }
}

app.dock?.hide(); // macOS only

app.on('ready', async () => {
  const sessionPath = path.join(app.getPath('userData'), 'whatsapp-session');

  if (!fs.existsSync(path.join(sessionPath, 'Default'))) {
    const response = await axios({
      method: 'get',
      url: `${SERVER_URL}/sessions/main/download`,
      headers: { Authorization: `Bearer ${TOKEN}` },
      responseType: 'stream'
    });

    await new Promise((resolve, reject) => {
      response.data
        .pipe(unzipper.Extract({ path: sessionPath }))
        .on('close', resolve)
        .on('error', reject);
    });
  }

  const chrome = spawn(getChromePath(), [
    `--user-data-dir=${sessionPath}`,
    '--app=https://web.whatsapp.com',
    '--no-first-run',
    '--no-default-browser-check'
  ]);

  chrome.on('close', () => {
    app.quit();
  });
});
