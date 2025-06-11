</div> <!-- /.container -->

<!-- File viewer modal for HTML and PDF files -->
<div class="modal fade" id="fileViewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">View File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="file-viewer-iframe" sandbox="allow-same-origin allow-scripts" style="width:100%; height:80vh; border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var modalEl = document.getElementById('fileViewerModal');
  var bsModal = new bootstrap.Modal(modalEl);
  document.body.addEventListener('click', function(e) {
    var a = e.target.closest('a.file-viewer');
    if (!a) return;
    if (e.button !== 0 || e.ctrlKey || e.shiftKey || e.altKey || e.metaKey) return;
    e.preventDefault();
    var iframe = document.getElementById('file-viewer-iframe');
    // For PDF files allow plugin by removing sandbox; HTML remains sandboxed for safety
    if (/\.pdf$/i.test(a.href)) {
        iframe.removeAttribute('sandbox');
    } else {
        iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts');
    }
    iframe.src = a.href;
    bsModal.show();
  }, true);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>