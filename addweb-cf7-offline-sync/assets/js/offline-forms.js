(function(){
  'use strict';

  const settings = (typeof AFOS_SETTINGS !== 'undefined') ? AFOS_SETTINGS : {};
  const LOG_PREFIX = '[AFOS]';

  function log(){ if(settings.enableDebug && console) { console.log(LOG_PREFIX, ...arguments); } }
  function warn(){ if(console) { console.warn(LOG_PREFIX, ...arguments); } }

  // IndexedDB wrapper
  const DB_NAME = 'afos-db';
  const STORE = 'queue';
  const VERSION = 1;

  function openDb(){
    return new Promise((resolve, reject)=>{
      const req = indexedDB.open(DB_NAME, VERSION);
      req.onupgradeneeded = (e)=>{
        const db = e.target.result;
        if(!db.objectStoreNames.contains(STORE)){
          db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = ()=> resolve(req.result);
      req.onerror = ()=> reject(req.error);
    });
  }

  function addToQueue(payload){
    return openDb().then(db=> new Promise((resolve, reject)=>{
      const tx = db.transaction(STORE, 'readwrite');
      tx.oncomplete = ()=> resolve();
      tx.onerror = ()=> reject(tx.error);
      tx.objectStore(STORE).add(payload);
    }));
  }

  function getAllQueued(){
    return openDb().then(db=> new Promise((resolve, reject)=>{
      const tx = db.transaction(STORE, 'readonly');
      const store = tx.objectStore(STORE);
      const items = [];
      store.openCursor().onsuccess = (e)=>{
        const cursor = e.target.result;
        if(cursor){ items.push(cursor.value); cursor.continue(); } else { resolve(items); }
      };
      tx.onerror = ()=> reject(tx.error);
    }));
  }

  function removeFromQueue(id){
    return openDb().then(db=> new Promise((resolve, reject)=>{
      const tx = db.transaction(STORE, 'readwrite');
      tx.oncomplete = ()=> resolve();
      tx.onerror = ()=> reject(tx.error);
      tx.objectStore(STORE).delete(id);
    }));
  }

  async function syncQueued(){
    if(!navigator.onLine){ return; }
    const items = await getAllQueued();
    for(const item of items){
      try {
        await syncOne(item);
        await removeFromQueue(item.id);
        log('Synced queued submission', item);
      } catch(e){
        warn('Sync failed, will retry later', e);
      }
    }
  }

  function buildPayload(form){
    const formData = new FormData(form);
    const fields = {};
    for(const [k,v] of formData.entries()){
      if(k.startsWith('_')) continue; // CF7 internals
      if(fields[k]){
        if(Array.isArray(fields[k])) fields[k].push(v); else fields[k] = [fields[k], v];
      } else {
        fields[k] = v;
      }
    }
    const formIdEl = form.querySelector('input[name="_wpcf7"]');
    const formId = formIdEl ? parseInt(formIdEl.value, 10) : undefined;
    return { form_id: formId, form_title: document.title, fields, source: 'offline' };
  }

  async function syncOne(item){
    const url = settings.restUrl.replace(/\/$/, '') + '/submissions';
    const res = await fetch(url + '?_locale=user', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-afos-api-key': settings.apiKey || '',
      },
      body: JSON.stringify({ form_id: item.form_id, form_title: item.form_title, fields: item.fields, source: 'offline' })
    });
    if(!res.ok){ throw new Error('HTTP '+res.status); }
    return res.json();
  }

  function showNotice(form, message, type){
    const el = document.createElement('div');
    el.className = 'afos-notice ' + (type||'info');
    el.textContent = message;
    form.parentNode.insertBefore(el, form);
    setTimeout(()=>{ el.remove(); }, 5000);
  }

  function setCf7Response(form, message, ok){
    const container = form.closest('.wpcf7');
    if(container){
      container.classList.remove('submitting');
      container.classList.add(ok ? 'sent' : 'failed');
    }
    form.classList.remove('submitting');
    form.removeAttribute('aria-busy');
    const btns = form.querySelectorAll('button, input[type="submit"], input[type="button"]');
    btns.forEach(b=> b.disabled = false);

    let out = container ? container.querySelector('.wpcf7-response-output') : null;
    if(!out){
      out = document.createElement('div');
      out.className = 'wpcf7-response-output';
      (container || form).appendChild(out);
    }
    out.textContent = message;
    out.className = 'wpcf7-response-output ' + (ok ? 'wpcf7-mail-sent-ok' : 'wpcf7-validation-errors');

    try {
      const detail = { status: ok ? 'mail_sent' : 'failed', message: message, apiResponse: { message, status: ok ? 'mail_sent' : 'failed' } };
      form.dispatchEvent(new CustomEvent('wpcf7submit', { bubbles: true, detail }));
      if(ok){ form.dispatchEvent(new CustomEvent('wpcf7mailsent', { bubbles: true, detail })); }
    } catch(_) {}
  }

  function handleSubmit(e){
    const form = e.target;
    if(!form || form.getAttribute('data-offline-form') !== 'true') return;
    if(navigator.onLine) return; // let normal submit
    e.preventDefault();
    // Prevent CF7 JS from proceeding (stop spinner/loader state)
    if(typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    if(typeof e.stopPropagation === 'function') e.stopPropagation();
    // Ensure any CF7 submitting classes are cleared
    setTimeout(()=>{
      setCf7Response(form, form.getAttribute('data-offline-success') || 'Saved offline. We will submit it automatically when you are back online.', true);
    }, 0);

    try {
      const payload = buildPayload(form);
      addToQueue(payload).then(()=>{
        // Visual confirmation and reset
        showNotice(form, form.getAttribute('data-offline-message') || 'You are offline. Your submission is saved.', 'info');
        try { form.reset(); } catch(_) {}
        syncQueued();
      }).catch((err)=>{
        showNotice(form, 'Failed to save offline. Please try again later.', 'error');
        warn('Queue add failed', err);
        setCf7Response(form, 'Failed to save offline. Please try again later.', false);
      });
    } catch(err){
      warn('handleSubmit error', err);
      setCf7Response(form, 'An unexpected error occurred while saving offline.', false);
    }
  }

  function registerSW(){
    if('serviceWorker' in navigator){
      navigator.serviceWorker.register(settings.swUrl).catch(()=>{});
    }
  }

  function init(){
    registerSW();
    document.addEventListener('submit', handleSubmit, true);
    window.addEventListener('online', ()=> { setTimeout(syncQueued, 1000); });
    setTimeout(syncQueued, 2000);
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();