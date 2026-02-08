const fetch = require('node-fetch');
const sodium = require('tweetsodium');

async function setSecret({ owner, repo, name, value, token }){
  const pubRes = await fetch(`https://api.github.com/repos/${owner}/${repo}/actions/secrets/public-key`, {
    headers: { Authorization: `token ${token}`, Accept: 'application/vnd.github+json' }
  });
  if(!pubRes.ok) throw new Error('Failed to fetch public key: ' + await pubRes.text());
  const pub = await pubRes.json();
  const publicKey = pub.key;
  const keyId = pub.key_id;

  const messageBytes = Buffer.from(value);
  const publicKeyBytes = Buffer.from(publicKey, 'base64');
  const encryptedBytes = sodium.seal(messageBytes, publicKeyBytes);
  const encrypted = Buffer.from(encryptedBytes).toString('base64');

  const putRes = await fetch(`https://api.github.com/repos/${owner}/${repo}/actions/secrets/${name}`, {
    method: 'PUT',
    headers: { Authorization: `token ${token}`, 'Content-Type': 'application/json', Accept: 'application/vnd.github+json' },
    body: JSON.stringify({ encrypted_value: encrypted, key_id: keyId })
  });
  if(!putRes.ok) throw new Error('Failed to set secret: ' + await putRes.text());
  return true;
}

// CLI
if(require.main === module){
  (async ()=>{
    const [,, ownerRepo, name, value, token] = process.argv;
    if(!ownerRepo || !name || !value || !token){
      console.error('Usage: node set_secret.js owner/repo NAME VALUE GITHUB_TOKEN');
      process.exit(2);
    }
    const [owner, repo] = ownerRepo.split('/');
    try{
      await setSecret({ owner, repo, name, value, token });
      console.log(`Secret ${name} set`);
    }catch(err){
      console.error('Error:', err.message || err);
      process.exit(1);
    }
  })();
}
