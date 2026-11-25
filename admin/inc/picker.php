<!-- Media picker (used by the blog editor and the page editor) -->
<dialog class="modal" id="media-picker" aria-labelledby="mp-title">
  <form method="dialog" class="modal__head"><h2 id="mp-title">Choose a file</h2><button class="btn btn--ghost" value="cancel" aria-label="Close">&times;</button></form>
  <div class="modal__tools">
    <label class="btn btn--primary upload-btn">Upload new<input type="file" data-mp-upload hidden></label>
    <input type="search" placeholder="Search files" data-mp-search>
    <span class="muted small" data-mp-status></span>
  </div>
  <div class="mp-grid" data-mp-grid></div>
</dialog>
