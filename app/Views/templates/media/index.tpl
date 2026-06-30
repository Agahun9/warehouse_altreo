<style>
  .media-library {
    --glass: rgba(255,255,255,.68);
    --glass-border: rgba(255,255,255,.82);
    --ink: #172033;
    --muted: #64748b;
    position: relative;
    isolation: isolate;
    min-height: calc(100vh - 70px);
    color: var(--ink);
  }
  .media-library::before,
  .media-library::after {
    content: '';
    position: fixed;
    z-index: -1;
    border-radius: 999px;
    filter: blur(12px);
    pointer-events: none;
  }
  .media-library::before {
    width: 430px; height: 430px; right: 3%; top: 9%;
    background: radial-gradient(circle, rgba(99,102,241,.23), rgba(99,102,241,0) 70%);
  }
  .media-library::after {
    width: 520px; height: 520px; left: 12%; bottom: -18%;
    background: radial-gradient(circle, rgba(14,165,233,.19), rgba(14,165,233,0) 70%);
  }
  .media-hero {
    padding: 1.5rem 1.65rem;
    border: 1px solid var(--glass-border);
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(255,255,255,.86), rgba(255,255,255,.48));
    box-shadow: 0 18px 55px rgba(51,65,85,.10), inset 0 1px 0 rgba(255,255,255,.9);
    backdrop-filter: blur(22px) saturate(145%);
    -webkit-backdrop-filter: blur(22px) saturate(145%);
  }
  .media-hero-icon {
    width: 54px; height: 54px; flex: 0 0 54px; display: grid; place-items: center;
    border-radius: 18px; color: #fff; font-size: 1.5rem;
    background: linear-gradient(135deg, #6366f1, #0ea5e9);
    box-shadow: 0 12px 28px rgba(79,70,229,.27);
  }
  .media-count {
    padding: .62rem .9rem; border-radius: 999px; font-weight: 700; color: #4338ca;
    background: rgba(238,242,255,.76); border: 1px solid rgba(129,140,248,.25);
  }
  .glass-panel {
    border: 1px solid var(--glass-border) !important;
    border-radius: 24px !important;
    background: var(--glass) !important;
    box-shadow: 0 18px 50px rgba(51,65,85,.09), inset 0 1px 0 rgba(255,255,255,.95) !important;
    backdrop-filter: blur(22px) saturate(145%);
    -webkit-backdrop-filter: blur(22px) saturate(145%);
    overflow: hidden;
  }
  .media-drop-zone {
    position: relative; overflow: hidden; cursor: pointer; padding: 2.3rem !important;
    border: 1.5px dashed rgba(99,102,241,.42) !important; border-radius: 20px !important;
    background: linear-gradient(135deg, rgba(238,242,255,.7), rgba(240,249,255,.62)) !important;
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
  }
  .media-drop-zone::after {
    content: ''; position: absolute; width: 160px; height: 160px; right: -60px; top: -70px;
    border-radius: 50%; background: rgba(99,102,241,.11); filter: blur(2px);
  }
  .media-drop-zone:hover, .media-drop-zone.is-dragging {
    transform: translateY(-2px); border-color: #6366f1 !important;
    box-shadow: 0 15px 38px rgba(99,102,241,.14);
  }
  .media-upload-icon {
    width: 58px; height: 58px; display: grid; place-items: center; margin: 0 auto .75rem;
    border-radius: 19px; color: #fff; font-size: 1.65rem;
    background: linear-gradient(135deg, #6366f1, #38bdf8);
    box-shadow: 0 13px 30px rgba(99,102,241,.25);
  }
  .media-pending-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); gap: .8rem;
    text-align: left;
  }
  .media-pending-item {
    min-width: 0; padding: .48rem; border-radius: 15px;
    background: rgba(255,255,255,.78); border: 1px solid rgba(255,255,255,.92);
    box-shadow: 0 8px 22px rgba(51,65,85,.08);
  }
  .media-pending-thumb {
    height: 105px; border-radius: 11px; overflow: hidden; display: grid; place-items: center;
    background: linear-gradient(145deg, #eef2ff, #e0f2fe);
  }
  .media-pending-thumb img, .media-pending-thumb video { width: 100%; height: 100%; object-fit: contain; }
  .media-pending-thumb i { font-size: 2rem; color: #6366f1; }
  .media-pending-name { margin-top: .45rem; font-size: .78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .media-pending-size { font-size: .7rem; color: var(--muted); }
  .media-pending-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
  .media-library .form-control, .media-library .form-select {
    border-radius: 13px; border-color: rgba(148,163,184,.28); background-color: rgba(255,255,255,.72);
    box-shadow: inset 0 1px 2px rgba(15,23,42,.025);
  }
  .media-library .form-control:focus, .media-library .form-select:focus {
    border-color: rgba(99,102,241,.58); box-shadow: 0 0 0 .22rem rgba(99,102,241,.11);
  }
  .media-library .btn { border-radius: 12px; font-weight: 600; }
  .media-library .btn-primary, .media-library .btn-success {
    border: 0; background: linear-gradient(135deg, #6366f1, #0ea5e9);
    box-shadow: 0 8px 22px rgba(79,70,229,.20);
  }
  .media-library .btn-primary:hover, .media-library .btn-success:hover { filter: brightness(1.05); transform: translateY(-1px); }
  .media-card {
    border: 1px solid rgba(255,255,255,.88) !important; border-radius: 22px !important;
    background: rgba(255,255,255,.69) !important; overflow: hidden;
    box-shadow: 0 12px 34px rgba(51,65,85,.085), inset 0 1px 0 #fff !important;
    backdrop-filter: blur(18px) saturate(140%); -webkit-backdrop-filter: blur(18px) saturate(140%);
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
  }
  .media-card:hover { transform: translateY(-6px); border-color: rgba(165,180,252,.65) !important; box-shadow: 0 24px 50px rgba(51,65,85,.15) !important; }
  .media-preview {
    position: relative; height: 215px; margin: 9px; border-radius: 16px !important;
    background: linear-gradient(145deg, rgba(241,245,249,.9), rgba(226,232,240,.65));
    display: flex; align-items: center; justify-content: center; overflow: hidden;
  }
  .media-preview::after { content: ''; position: absolute; inset: 0; box-shadow: inset 0 0 0 1px rgba(255,255,255,.55); border-radius: inherit; pointer-events: none; }
  .media-preview img, .media-preview video { width: 100%; height: 100%; object-fit: contain; }
  .media-type-pill {
    position: absolute; z-index: 2; left: 10px; top: 10px; padding: .34rem .55rem;
    border: 1px solid rgba(255,255,255,.65); border-radius: 999px; color: #fff;
    background: rgba(15,23,42,.55); backdrop-filter: blur(10px); font-size: .72rem; font-weight: 700;
  }
  .media-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .media-card .card-footer { border-top: 1px solid rgba(148,163,184,.14); background: rgba(248,250,252,.48) !important; }
  .media-empty { border-radius: 24px; background: rgba(255,255,255,.62); backdrop-filter: blur(18px); }
  @media (max-width: 575.98px) { .media-hero { padding: 1.2rem; border-radius: 21px; } .media-preview { height: 230px; } }
</style>

<div class="container-fluid py-4 media-library">
  <div class="media-hero d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
      <div class="media-hero-icon"><i class="bi bi-images"></i></div>
      <div>
        <h1 class="h3 fw-bold mb-1">Biblioteka mediów</h1>
        <div class="text-muted">Twoje zdjęcia i filmy w jednym pięknym miejscu.</div>
      </div>
    </div>
    <span class="media-count"><i class="bi bi-collection me-1"></i> {count($items)} plików</span>
  </div>

  {if $canWrite}
    <form method="post" action="{$baseUrl}?controller=media&action=upload" enctype="multipart/form-data" class="card glass-panel mb-4" id="mediaUploadForm">
      <div class="card-body">
        <label class="media-drop-zone text-center d-block mb-0" for="mediaFiles" id="mediaDropZone">
          <div id="mediaDropPrompt">
            <span class="media-upload-icon"><i class="bi bi-cloud-arrow-up"></i></span>
            <div class="fw-bold fs-5">Przeciągnij zdjęcia lub filmy tutaj</div>
            <div class="text-muted small mb-2">albo kliknij, aby wybrać wiele plików</div>
            <span class="btn btn-primary px-3">Wybierz pliki</span>
          </div>
          <div id="mediaPendingPreview" class="d-none"></div>
          <input type="file" name="media_files[]" id="mediaFiles" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime,video/x-matroska" multiple required>
        </label>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <div class="small text-muted" id="mediaSelection">Dozwolone: JPG, PNG, WebP, GIF, AVIF, MP4, WebM, MOV, MKV. Maks. 250 MB na plik.</div>
          <button type="submit" class="btn btn-success" id="mediaUploadButton" disabled><i class="bi bi-upload me-1"></i> Wyślij media</button>
        </div>
      </div>
    </form>
  {/if}

  <form method="get" action="{$baseUrl}" class="card glass-panel mb-4">
    <input type="hidden" name="controller" value="media">
    <input type="hidden" name="action" value="index">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label">Szukaj mediów</label>
        <input type="search" class="form-control" name="q" value="{$query|escape:'html'}" placeholder="Nazwa pliku...">
      </div>
      <div class="col-md-3">
        <label class="form-label">Typ</label>
        <select class="form-select" name="type">
          <option value="">Wszystkie media</option>
          <option value="image" {if $type eq 'image'}selected{/if}>Zdjęcia</option>
          <option value="video" {if $type eq 'video'}selected{/if}>Filmy</option>
        </select>
      </div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search me-1"></i> Szukaj</button></div>
    </div>
  </form>

  <div class="row g-3">
    {foreach $items as $item}
      <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card media-card h-100">
          <div class="media-preview rounded-top">
            <span class="media-type-pill"><i class="bi {if $item.media_type eq 'image'}bi-image{else}bi-camera-video{/if} me-1"></i>{if $item.media_type eq 'image'}OBRAZ{else}WIDEO{/if}</span>
            {if $item.media_type eq 'image'}
              <img src="{$item.public_url|escape:'html'}" alt="{$item.file_name|escape:'html'}" loading="lazy">
            {else}
              <video src="{$item.public_url|escape:'html'}" preload="metadata" controls></video>
            {/if}
          </div>
          <div class="card-body">
            <div class="fw-semibold media-name mb-1" title="{$item.file_name|escape:'html'}">{$item.file_name|escape:'html'}</div>
            <div class="small text-muted mb-3">{$item.size_label|escape:'html'} · {$item.created_at|escape:'html'}</div>
            <div class="input-group input-group-sm mb-2">
              <input class="form-control media-url" value="{$item.public_url|escape:'html'}" readonly aria-label="Publiczny URL">
              <button type="button" class="btn btn-primary copy-media-url" title="Kopiuj link"><i class="bi bi-clipboard"></i></button>
            </div>
            <a class="btn btn-sm btn-outline-secondary w-100" href="{$item.public_url|escape:'html'}" target="_blank" rel="noopener">Otwórz publiczny plik</a>
          </div>
          {if $canWrite}
            <div class="card-footer bg-white">
              <form method="post" action="{$baseUrl}?controller=media&action=rename" class="d-flex gap-1 mb-2">
                <input type="hidden" name="id" value="{$item.id}">
                <input class="form-control form-control-sm" name="name" value="{$item.base_name|escape:'html'}" required aria-label="Nowa nazwa">
                <button class="btn btn-sm btn-outline-primary" title="Zmień nazwę"><i class="bi bi-check-lg"></i></button>
              </form>
              <form method="post" action="{$baseUrl}?controller=media&action=delete" onsubmit="return confirm('Usunąć plik {$item.file_name|escape:'javascript'}? Publiczny link przestanie działać.');">
                <input type="hidden" name="id" value="{$item.id}">
                <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash me-1"></i> Usuń</button>
              </form>
            </div>
          {/if}
        </div>
      </div>
    {foreachelse}
      <div class="col-12"><div class="media-empty border text-center py-5 mb-0"><i class="bi bi-images fs-1 text-muted"></i><div class="fw-semibold mt-2">Brak mediów spełniających kryteria</div></div></div>
    {/foreach}
  </div>
</div>

<script>
{literal}
(function () {
  document.querySelectorAll('.copy-media-url').forEach(function (button) {
    button.addEventListener('click', async function () {
      const input = this.closest('.input-group').querySelector('.media-url');
      try {
        await navigator.clipboard.writeText(input.value);
      } catch (error) {
        input.select();
        document.execCommand('copy');
      }
      const original = this.innerHTML;
      this.innerHTML = '<i class="bi bi-check-lg"></i>';
      window.setTimeout(() => { this.innerHTML = original; }, 1200);
    });
  });

  const input = document.getElementById('mediaFiles');
  const zone = document.getElementById('mediaDropZone');
  const button = document.getElementById('mediaUploadButton');
  const selection = document.getElementById('mediaSelection');
  const prompt = document.getElementById('mediaDropPrompt');
  const preview = document.getElementById('mediaPendingPreview');
  if (!input || !zone || !button || !selection || !prompt || !preview) return;
  let previewUrls = [];

  function readableFileSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
  }

  function clearPreviewUrls() {
    previewUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    previewUrls = [];
  }

  function renderPendingFiles(files) {
    clearPreviewUrls();
    preview.innerHTML = '';
    if (!files.length) {
      prompt.classList.remove('d-none');
      preview.classList.add('d-none');
      return;
    }
    prompt.classList.add('d-none');
    preview.classList.remove('d-none');

    const heading = document.createElement('div');
    heading.className = 'media-pending-heading';
    heading.innerHTML = '<div><div class="fw-bold">Gotowe do wysłania</div><div class="small text-muted">Kliknij tutaj, aby zmienić wybrane pliki</div></div>'
      + '<span class="media-count"><i class="bi bi-check2-circle me-1"></i>' + files.length + '</span>';
    preview.appendChild(heading);

    const grid = document.createElement('div');
    grid.className = 'media-pending-grid';
    files.forEach(function (file) {
      const item = document.createElement('div');
      item.className = 'media-pending-item';
      const thumb = document.createElement('div');
      thumb.className = 'media-pending-thumb';
      const url = URL.createObjectURL(file);
      previewUrls.push(url);
      if (file.type.indexOf('image/') === 0) {
        const image = document.createElement('img');
        image.src = url;
        image.alt = file.name;
        thumb.appendChild(image);
      } else if (file.type.indexOf('video/') === 0) {
        const video = document.createElement('video');
        video.src = url;
        video.muted = true;
        video.preload = 'metadata';
        thumb.appendChild(video);
      } else {
        thumb.innerHTML = '<i class="bi bi-file-earmark"></i>';
      }
      const name = document.createElement('div');
      name.className = 'media-pending-name';
      name.title = file.name;
      name.textContent = file.name;
      const size = document.createElement('div');
      size.className = 'media-pending-size';
      size.textContent = readableFileSize(file.size);
      item.appendChild(thumb);
      item.appendChild(name);
      item.appendChild(size);
      grid.appendChild(item);
    });
    preview.appendChild(grid);
  }

  function updateSelection() {
    const files = input.files ? Array.from(input.files) : [];
    const count = files.length;
    button.disabled = count === 0;
    selection.textContent = count ? 'Wybrano plików: ' + count : 'Nie wybrano plików.';
    renderPendingFiles(files);
  }
  input.addEventListener('change', updateSelection);
  ['dragenter', 'dragover'].forEach(function (eventName) {
    zone.addEventListener(eventName, function (event) {
      event.preventDefault();
      zone.classList.add('is-dragging');
    });
  });
  ['dragleave', 'drop'].forEach(function (eventName) {
    zone.addEventListener(eventName, function (event) {
      event.preventDefault();
      zone.classList.remove('is-dragging');
    });
  });
  zone.addEventListener('drop', function (event) {
    if (event.dataTransfer && event.dataTransfer.files.length) {
      input.files = event.dataTransfer.files;
      updateSelection();
    }
  });
})();
{/literal}
</script>
