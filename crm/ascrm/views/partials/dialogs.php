<dialog id="confirm-dialog" aria-labelledby="confirm-title">
  <div class="dlg-b"><h2 id="confirm-title">Please confirm</h2><p id="confirm-text" class="muted"></p></div>
  <div class="dlg-f"><button type="button" class="btn btn-ghost" id="confirm-cancel">Cancel</button><button type="button" class="btn btn-primary" id="confirm-ok">Confirm</button></div>
</dialog>
<dialog id="idle-dialog" aria-labelledby="idle-title">
  <div class="dlg-b"><h2 id="idle-title">Still there?</h2><p class="muted">For your security you will be signed out shortly because of inactivity.</p></div>
  <div class="dlg-f"><a class="btn btn-ghost" href="<?= h(url('/login')) ?>">Sign out</a><button type="button" class="btn btn-primary" id="idle-stay">Stay signed in</button></div>
</dialog>
<dialog id="palette" class="palette" aria-label="Search">
  <div class="palette-input"><?= icon('search') ?><input type="text" placeholder="Search cases, clients and pages" aria-label="Search" autocomplete="off"><kbd>Esc</kbd></div>
  <ul class="palette-results"></ul>
  <div class="palette-foot"><span><kbd>&uarr;</kbd> <kbd>&darr;</kbd> to move</span><span><kbd>Enter</kbd> to open</span><span><kbd>Ctrl</kbd> <kbd>K</kbd> to toggle</span></div>
</dialog>
