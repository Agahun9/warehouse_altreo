<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Tiny File Manager</h3>
          <a href="{$fileManagerUrl|escape}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer">Otworz w nowej karcie</a>
        </div>
        <div class="card-body p-0">
          <iframe
            src="{$fileManagerUrl|escape}"
            title="Tiny File Manager"
            style="display:block;width:100%;height:calc(100vh - 260px);min-height:720px;border:0;background:#fff;"
            loading="lazy"
            referrerpolicy="no-referrer"
          ></iframe>
        </div>
      </div>
    </div>
  </div>
</main>
