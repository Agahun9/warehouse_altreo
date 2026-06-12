<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-lg-7">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-lg-5">
          <ol class="breadcrumb float-lg-end mb-0">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <style>
        .taskboard-shell {
          --taskboard-surface: rgba(255, 255, 255, 0.88);
          --taskboard-border: rgba(148, 163, 184, 0.22);
          --taskboard-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
          --taskboard-soft-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
          --taskboard-text: #0f172a;
          --taskboard-muted: #64748b;
          --taskboard-bg:
            radial-gradient(circle at top left, rgba(14, 165, 233, 0.12), transparent 28%),
            radial-gradient(circle at top right, rgba(16, 185, 129, 0.12), transparent 24%),
            linear-gradient(180deg, #f8fbff 0%, #eef3f9 100%);
          position: relative;
          overflow: hidden;
          padding: 1.15rem;
          border: 1px solid rgba(255, 255, 255, 0.72);
          border-radius: 1.6rem;
          background: var(--taskboard-bg);
          box-shadow: var(--taskboard-shadow);
        }

        body.taskboard-edit-layer-active .app-main,
        body.taskboard-edit-layer-active .app-header,
        body.taskboard-edit-layer-active .app-sidebar {
          filter: blur(3px);
          transition: filter 0.18s ease;
        }

        body.taskboard-edit-layer-active .modal.show,
        body.taskboard-edit-layer-active .offcanvas.show {
          filter: none;
        }

        .taskboard-shell::before,
        .taskboard-shell::after {
          content: '';
          position: absolute;
          border-radius: 50%;
          filter: blur(8px);
          pointer-events: none;
        }

        .taskboard-shell::before {
          width: 14rem;
          height: 14rem;
          top: -6rem;
          right: -4rem;
          background: rgba(56, 189, 248, 0.12);
        }

        .taskboard-shell::after {
          width: 11rem;
          height: 11rem;
          left: -3rem;
          bottom: -5rem;
          background: rgba(52, 211, 153, 0.12);
        }

        .taskboard-sidebar-card,
        .taskboard-board-stage,
        .taskboard-detail-card,
        .taskboard-summary-card {
          position: relative;
          z-index: 1;
          border: 1px solid var(--taskboard-border);
          border-radius: 1.35rem;
          background: var(--taskboard-surface);
          backdrop-filter: blur(14px);
          box-shadow: var(--taskboard-soft-shadow);
        }

        .taskboard-sidebar-card,
        .taskboard-detail-card {
          padding: 1rem;
        }

        .taskboard-board-list {
          display: grid;
          gap: 0.8rem;
        }

        .taskboard-board-link {
          display: block;
          padding: 0.95rem 1rem;
          border-radius: 1.1rem;
          border: 1px solid rgba(148, 163, 184, 0.18);
          color: var(--taskboard-text);
          text-decoration: none;
          background: rgba(255, 255, 255, 0.6);
          transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .taskboard-board-link:hover,
        .taskboard-board-link.is-active {
          color: var(--taskboard-text);
          transform: translateY(-2px);
          box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
          border-color: rgba(14, 116, 144, 0.28);
          background: rgba(255, 255, 255, 0.92);
        }

        .taskboard-board-link.is-active {
          outline: 2px solid rgba(14, 116, 144, 0.18);
        }

        .taskboard-board-title {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.75rem;
          font-weight: 700;
          margin-bottom: 0.35rem;
        }

        .taskboard-board-dot {
          width: 0.8rem;
          height: 0.8rem;
          flex: 0 0 auto;
          border-radius: 50%;
          box-shadow: 0 0 0 0.18rem rgba(255, 255, 255, 0.85);
        }

        .taskboard-board-meta,
        .taskboard-stat-label,
        .taskboard-empty-note,
        .taskboard-task-meta,
        .taskboard-subcopy,
        .taskboard-note-meta,
        .taskboard-attachment-meta {
          color: var(--taskboard-muted);
        }

        .taskboard-board-meta {
          font-size: 0.82rem;
        }

        .taskboard-chip,
        .taskboard-detail-pill {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.35rem 0.72rem;
          border-radius: 999px;
          font-size: 0.78rem;
          font-weight: 700;
        }

        .taskboard-chip {
          background: rgba(255, 255, 255, 0.16);
          color: #fff;
        }

        .taskboard-toolbar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: space-between;
        }

        .taskboard-header-mini {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 1rem;
        }

        .taskboard-compact-head {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: space-between;
          gap: 1rem;
          padding: 0px 1.1rem;
        }

        .taskboard-compact-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 0.65rem;
          align-items: center;
        }

        .taskboard-toolbar-filters {
          display: flex;
          flex-wrap: wrap;
          gap: 0.35rem;
          align-items: center;
        }

        .taskboard-filter-strip {
          padding: 0.45rem 0.55rem;
          border-radius: 0.8rem;
        }

        .taskboard-filter-strip .taskboard-filters-collapse {
          margin-top: 0;
        }

        .taskboard-filter-button {
          min-height: 1.85rem;
          padding: 0.22rem 0.5rem;
          border-radius: 999px;
          font-size: 0.76rem;
          line-height: 1.1;
        }

        .taskboard-form-surface {
          padding: 1rem;
          border-radius: 1.15rem;
          border: 1px dashed rgba(148, 163, 184, 0.35);
          background: rgba(255, 255, 255, 0.64);
        }

        .taskboard-columns {
          display: flex;
          flex-wrap: nowrap;
          gap: 1rem;
          align-items: flex-start;
          overflow-x: auto;
          padding-bottom: 0.35rem;
        }

        .taskboard-stage-col {
          flex: 0 0 clamp(280px, calc((100% * var(--taskboard-span, 3)) / 12), 100%);
        }

        .taskboard-board-stage {
          overflow: hidden;
          min-height: 18rem;
        }

        .taskboard-stage-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.7rem;
          padding: 1rem 1rem 0.8rem;
          border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }

        .taskboard-stage-body {
          min-height: 14rem;
          padding: 0.95rem;
          display: grid;
          gap: 0.85rem;
          transition: background-color 0.18s ease, transform 0.18s ease;
        }

        .taskboard-stage-body.is-drag-target {
          background: rgba(13, 110, 253, 0.06);
        }

        .taskboard-task-card {
          display: block;
          padding: 0.95rem;
          border-radius: 1.15rem;
          border: 1px solid rgba(148, 163, 184, 0.18);
          background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96));
          box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
          color: var(--taskboard-text);
          text-decoration: none;
          transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
          animation: taskboardCardIn 0.32s ease both;
          cursor: pointer;
        }

        .taskboard-task-card:hover,
        .taskboard-task-card.is-selected {
          color: var(--taskboard-text);
          transform: translateY(-3px);
          border-color: rgba(13, 110, 253, 0.26);
          box-shadow: 0 18px 36px rgba(15, 23, 42, 0.1);
        }

        .taskboard-task-card.is-dragging {
          opacity: 0.5;
          transform: rotate(1deg) scale(0.98);
          cursor: pointer;
        }

        .taskboard-task-card.is-overdue {
          border-color: rgba(220, 38, 38, 0.28);
          background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(254, 242, 242, 0.92));
        }

        .taskboard-task-card.is-newly-created {
          animation: taskboardNewTaskIn 0.78s cubic-bezier(0.2, 0.85, 0.25, 1) both;
          border-color: rgba(16, 185, 129, 0.48);
        }

        .taskboard-task-card.is-panel-target {
          box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.13), 0 18px 36px rgba(15, 23, 42, 0.1);
        }

        .taskboard-task-topline,
        .taskboard-task-bottomline,
        .taskboard-detail-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.55rem;
          align-items: center;
          justify-content: space-between;
        }

        .taskboard-task-title {
          margin: 0.6rem 0 0.5rem;
          font-size: 1rem;
          font-weight: 700;
          line-height: 1.3;
        }

        .taskboard-task-image {
          width: 100%;
          height: 8.5rem;
          object-fit: cover;
          display: block;
          border-radius: 0.95rem;
          margin-bottom: 0.75rem;
          border: 1px solid rgba(148, 163, 184, 0.18);
          background: #f8fafc;
        }

        .taskboard-priority {
          display: inline-flex;
          align-items: center;
          gap: 0.32rem;
          padding: 0.24rem 0.58rem;
          border-radius: 999px;
          font-size: 0.73rem;
          font-weight: 700;
          color: #fff;
        }

        .taskboard-avatar {
          width: 1.95rem;
          height: 1.95rem;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          font-size: 0.8rem;
          font-weight: 700;
          color: #0f172a;
          background: linear-gradient(135deg, rgba(186, 230, 253, 0.95), rgba(167, 243, 208, 0.95));
        }

        .taskboard-avatar.-small {
          width: 1.65rem;
          height: 1.65rem;
          font-size: 0.7rem;
        }

        .taskboard-offcanvas {
          --bs-offcanvas-width: min(560px, 94vw);
          transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.34s ease;
        }

        .taskboard-offcanvas.showing,
        .taskboard-offcanvas.show {
          animation: taskboardPanelSlideGlow 0.42s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .taskboard-offcanvas .offcanvas-header,
        .taskboard-modal .modal-header {
          border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }

        .taskboard-offcanvas .offcanvas-body,
        .taskboard-modal .modal-body {
          background: linear-gradient(180deg, #fbfdff 0%, #f3f7fb 100%);
        }

        .taskboard-modal .modal-content {
          border: 1px solid rgba(148, 163, 184, 0.2);
          border-radius: 1.4rem;
          overflow: hidden;
          box-shadow: 0 24px 50px rgba(15, 23, 42, 0.16);
        }

        .taskboard-detail-inline-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          align-items: center;
        }

        .taskboard-header-actions {
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
        }

        .taskboard-status-builder {
          display: grid;
          gap: 0.7rem;
        }

        .taskboard-status-row {
          display: grid;
          grid-template-columns: minmax(0, 1fr) auto auto auto;
          gap: 0.6rem;
          align-items: center;
        }

        .taskboard-status-row .form-control-color {
          width: 3.2rem;
          min-width: 3.2rem;
        }

        .taskboard-status-row .form-select {
          min-width: 5.4rem;
        }

        .taskboard-status-actions {
          display: inline-flex;
          align-items: center;
          gap: 0.4rem;
        }

        .taskboard-detail-card {
          position: static;
          padding: 0;
          border: 0;
          background: transparent;
          box-shadow: none;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-hero {
          animation: taskboardDetailHeroIn 0.42s ease both;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section,
        .taskboard-detail-card.is-animated .taskboard-detail-toolbar,
        .taskboard-detail-card.is-animated .taskboard-subcopy,
        .taskboard-detail-card.is-animated .taskboard-autosave-status {
          animation: taskboardDetailSectionIn 0.42s ease both;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-toolbar {
          animation-delay: 0.04s;
        }

        .taskboard-detail-card.is-animated .taskboard-subcopy,
        .taskboard-detail-card.is-animated .taskboard-autosave-status {
          animation-delay: 0.08s;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section:nth-of-type(1) {
          animation-delay: 0.1s;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section:nth-of-type(2) {
          animation-delay: 0.14s;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section:nth-of-type(3) {
          animation-delay: 0.18s;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section:nth-of-type(4) {
          animation-delay: 0.22s;
        }

        .taskboard-detail-card.is-animated .taskboard-detail-section:nth-of-type(5) {
          animation-delay: 0.26s;
        }

        .taskboard-detail-hero {
          padding: 1rem;
          margin-bottom: 0.95rem;
          border-radius: 1.1rem;
          border: 1px solid rgba(14, 116, 144, 0.2);
          background: linear-gradient(135deg, rgba(240, 253, 250, 0.96), rgba(239, 246, 255, 0.96));
        }

        .taskboard-detail-hero-title {
          font-size: 1.05rem;
          font-weight: 800;
          color: #0f172a;
          line-height: 1.25;
        }

        .taskboard-detail-title-input {
          min-height: 2.55rem;
          border-color: rgba(15, 23, 42, 0.12);
          font-size: 1.02rem;
          font-weight: 800;
        }

        .taskboard-detail-badges {
          display: flex;
          flex-wrap: wrap;
          gap: 0.45rem;
          margin-top: 0.7rem;
        }

        .taskboard-detail-badge {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.35rem 0.6rem;
          border-radius: 999px;
          background: #fff;
          border: 1px solid rgba(148, 163, 184, 0.22);
          color: #334155;
          font-size: 0.78rem;
          font-weight: 700;
        }

        .taskboard-autosave-status {
          min-height: 1.25rem;
          font-size: 0.78rem;
          color: #64748b;
        }

        .taskboard-detail-section {
          padding: 0.95rem;
          border-radius: 1rem;
          border: 1px solid rgba(148, 163, 184, 0.18);
          background: rgba(255, 255, 255, 0.92);
          box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
        }

        .taskboard-detail-section + .taskboard-detail-section {
          margin-top: 0.85rem;
        }

        .taskboard-detail-toolbar {
          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 0.7rem;
          margin-bottom: 0.9rem;
        }

        .taskboard-detail-meta-card {
          padding: 0.75rem 0.85rem;
          border-radius: 1rem;
          border: 1px solid rgba(148, 163, 184, 0.16);
          background: rgba(255, 255, 255, 0.86);
        }

        .taskboard-detail-meta-card .form-label {
          font-size: 0.72rem;
          margin-bottom: 0.35rem;
          color: var(--taskboard-muted);
        }

        .taskboard-detail-meta-card .form-select,
        .taskboard-detail-meta-card .form-control {
          min-height: 2.55rem;
        }

        .taskboard-section-title {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.75rem;
          margin-bottom: 0.75rem;
        }

        .taskboard-section-heading {
          display: inline-flex;
          align-items: center;
          gap: 0.55rem;
          min-width: 0;
        }

        .taskboard-section-icon {
          width: 2rem;
          height: 2rem;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          border-radius: 0.75rem;
          color: #fff;
          background: #0d9488;
          box-shadow: 0 8px 18px rgba(13, 148, 136, 0.24);
        }

        .taskboard-section-icon.-blue {
          background: #2563eb;
          box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
        }

        .taskboard-section-icon.-amber {
          background: #d97706;
          box-shadow: 0 8px 18px rgba(217, 119, 6, 0.22);
        }

        .taskboard-section-icon.-violet {
          background: #7c3aed;
          box-shadow: 0 8px 18px rgba(124, 58, 237, 0.2);
        }

        .taskboard-section-icon.-green {
          background: #16a34a;
          box-shadow: 0 8px 18px rgba(22, 163, 74, 0.22);
        }

        .taskboard-checklist {
          display: grid;
          gap: 0.65rem;
        }

        .taskboard-subtask-row {
          display: flex;
          align-items: center;
          gap: 0.65rem;
          padding: 0.55rem 0.7rem;
          border-radius: 0.85rem;
          border: 1px solid rgba(148, 163, 184, 0.14);
          background: rgba(255, 255, 255, 0.88);
          transition: transform 0.18s ease, background-color 0.18s ease;
        }

        .taskboard-subtask-row.is-done {
          background: rgba(236, 253, 245, 0.95);
        }

        .taskboard-subtask-row.is-done .taskboard-subtask-label {
          color: #4b5563;
          text-decoration: line-through;
        }

        .taskboard-subtask-checkbox {
          width: 1.2rem;
          height: 1.2rem;
          margin: 0;
          accent-color: #10b981;
          cursor: pointer;
        }

        .taskboard-subtask-label {
          font-size: 0.92rem;
        }

        .taskboard-item-delete {
          width: 1.85rem;
          height: 1.85rem;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          padding: 0;
          border-radius: 999px;
        }

        .taskboard-note-list,
        .taskboard-attachment-list {
          display: grid;
          gap: 0.8rem;
        }

        .taskboard-note-item,
        .taskboard-attachment-item {
          padding: 0.7rem 0.8rem;
          border-radius: 1rem;
          border: 1px solid rgba(148, 163, 184, 0.16);
          background: rgba(255, 255, 255, 0.88);
        }

        .taskboard-attachment-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.55rem;
        }

        .taskboard-attachment-link {
          color: var(--taskboard-text);
          text-decoration: none;
          font-weight: 600;
        }

        .taskboard-attachment-link:hover {
          color: #0d6efd;
        }

        .taskboard-empty-state {
          padding: 2rem 1rem;
          text-align: center;
          color: var(--taskboard-muted);
        }

        .taskboard-empty-state i {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 4rem;
          height: 4rem;
          margin-bottom: 0.85rem;
          border-radius: 50%;
          background: rgba(13, 110, 253, 0.08);
          color: #0d6efd;
          font-size: 1.4rem;
        }

        .taskboard-filters-collapse {
          margin-top: 0.85rem;
        }

        .taskboard-shell .col-xl-3,
        .taskboard-shell .col-xl-9 {
          flex: 0 0 100%;
          max-width: 100%;
        }

        .taskboard-archive-shell {
          margin-top: 1rem;
        }

        .taskboard-archive-card {
          border: 1px solid rgba(148, 163, 184, 0.2);
          border-radius: 1.2rem;
          background: rgba(255, 255, 255, 0.72);
          overflow: hidden;
        }

        .taskboard-archive-toggle {
          width: 100%;
          padding: 1rem 1.1rem;
          border: 0;
          background: transparent;
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.75rem;
          font-weight: 700;
          color: var(--taskboard-text);
        }

        .taskboard-archive-list {
          padding: 0 1rem 1rem;
          display: grid;
          gap: 0.75rem;
        }

        .taskboard-archive-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.8rem;
          padding: 0.85rem 0.95rem;
          border-radius: 1rem;
          border: 1px solid rgba(148, 163, 184, 0.16);
          background: rgba(248, 250, 252, 0.92);
        }

        .taskboard-archive-item.is-selected {
          border-color: rgba(13, 110, 253, 0.26);
          box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .taskboard-inline-actions {
          display: inline-flex;
          align-items: center;
          gap: 0.45rem;
        }

        .taskboard-pulse {
          animation: taskboardPulse 0.26s ease;
        }

        #taskboardTaskDetailsBody.is-refreshing {
          opacity: 0.72;
          transition: opacity 0.16s ease;
        }

        #taskboardTaskDetailsBody.just-updated .taskboard-detail-card {
          animation: taskboardPanelUpdated 0.52s ease;
        }

        @keyframes taskboardCardIn {
          from {
            opacity: 0;
            transform: translateY(10px) scale(0.98);
          }

          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }

        @keyframes taskboardNewTaskIn {
          0% {
            opacity: 0;
            transform: translateY(-12px) scale(0.94);
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
          }

          42% {
            opacity: 1;
            transform: translateY(0) scale(1.025);
            box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.18), 0 20px 38px rgba(15, 23, 42, 0.12);
          }

          100% {
            opacity: 1;
            transform: translateY(0) scale(1);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
          }
        }

        @keyframes taskboardPanelSlideGlow {
          0% {
            filter: drop-shadow(-18px 0 26px rgba(13, 110, 253, 0));
          }

          55% {
            filter: drop-shadow(-18px 0 26px rgba(13, 110, 253, 0.16));
          }

          100% {
            filter: drop-shadow(-18px 0 26px rgba(13, 110, 253, 0));
          }
        }

        @keyframes taskboardDetailHeroIn {
          from {
            opacity: 0;
            transform: translateY(12px) scale(0.98);
          }

          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }

        @keyframes taskboardDetailSectionIn {
          from {
            opacity: 0;
            transform: translateY(10px);
          }

          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        @keyframes taskboardPulse {
          0% {
            transform: scale(1);
          }

          50% {
            transform: scale(1.018);
          }

          100% {
            transform: scale(1);
          }
        }

        @keyframes taskboardPanelUpdated {
          0% {
            box-shadow: 0 0 0 rgba(16, 185, 129, 0);
            transform: translateY(0);
          }

          35% {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
            transform: translateY(-2px);
          }

          100% {
            box-shadow: 0 0 0 rgba(16, 185, 129, 0);
            transform: translateY(0);
          }
        }

        @media (max-width: 1399.98px) {
          .taskboard-columns {
            gap: 0.85rem;
          }
        }

        @media (max-width: 991.98px) {
          .taskboard-shell {
            padding: 0.85rem;
          }

          .taskboard-detail-toolbar {
            grid-template-columns: 1fr;
          }

          .taskboard-stage-col {
            flex-basis: min(88vw, 360px);
          }
        }
      </style>

      <div class="taskboard-shell">
        <div class="row g-4">
          <div class="col-12">
            {if $selectedBoard}
              <div class="taskboard-sidebar-card mb-4">
                <div class="taskboard-compact-head">
                  <div class="taskboard-header-mini">
                    <div class="text-uppercase small fw-bold text-secondary">Aktywna tablica</div>
                    <h2 class="h5 mb-1 d-flex align-items-center gap-2">
                      <span class="taskboard-board-dot" style="background: {$selectedBoard.accent_color|default:'#0d6efd'|escape};"></span>
                      {$selectedBoard.name|escape}
                    </h2>
                    <div class="small text-secondary">
                      {$boardTaskCount|default:0} zadan
                      {if $selectedBoard.is_archived|default:0} - archiwalna{/if}
                    </div>
                  </div>
                  <div class="taskboard-compact-actions">
                    <div class="taskboard-detail-inline-meta">
                      {foreach $allStatusDefinitions as $statusKey => $statusMeta}
                        <span class="badge text-bg-light">{$statusMeta.label|escape} {$boardProgress[$statusKey]|default:0}</span>
                      {/foreach}
                    </div>
                    {if $canWriteTaskboard}
                      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskboardCreateTaskModal">Nowe zadanie</button>
                    {/if}
                    {if $canWriteTaskboard}
                      <button type="button" class="btn btn-light btn-sm" data-bs-toggle="offcanvas" data-bs-target="#taskboardBoardSettings" aria-controls="taskboardBoardSettings" title="Ustawienia tablicy">
                        <i class="bi bi-gear"></i>
                      </button>
                    {/if}
                  </div>
                </div>
              </div>

              <div class="taskboard-sidebar-card taskboard-filter-strip mb-3">
                <div class="taskboard-filters-collapse show" id="taskboardTeamFilters">
                  <div class="taskboard-toolbar-filters">
                    <button type="button" class="btn btn-primary btn-sm taskboard-filter-button" data-taskboard-assignee-filter="">Wszyscy</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm taskboard-filter-button" data-taskboard-assignee-filter="unassigned">Bez osoby</button>
                    {foreach $assignedFilterUsers as $user}
                      <button type="button" class="btn btn-outline-secondary btn-sm taskboard-filter-button" data-taskboard-assignee-filter="{$user.id}">{$user.label|truncate:18|escape}</button>
                    {/foreach}
                  </div>
                </div>
              </div>

              <div class="row g-3" id="taskboardColumns">
                {foreach $activeStatusDefinitions as $statusKey => $statusMeta}
                  {assign var=statusTasks value=$tasksByStatus[$statusKey]}
                  <section class="taskboard-stage-col" style="--taskboard-span: {$statusMeta.span|default:3};">
                    <div class="taskboard-board-stage">
                    <div class="taskboard-stage-head">
                      <div>
                        <div class="d-flex align-items-center gap-2">
                          <span class="d-inline-block rounded-circle" style="width: 0.8rem; height: 0.8rem; background: {$statusMeta.accent|escape};"></span>
                          <h4 class="h6 mb-0">{$statusMeta.label|escape}</h4>
                        </div>
                        <div class="small text-secondary mt-1">{$statusTasks|@count} zadan</div>
                      </div>
                      <span class="badge rounded-pill text-bg-light">{$statusTasks|@count}</span>
                    </div>
                    <div class="taskboard-stage-body" data-status="{$statusKey|escape}" data-board-id="{$selectedBoard.id}">
                      {if $statusTasks}
                        {foreach $statusTasks as $task}
                          {assign var=priorityMeta value=$priorityDefinitions[$task.priority]|default:$priorityDefinitions.medium}
                          <article
                            class="taskboard-task-card{if $selectedTask and $selectedTask.id eq $task.id} is-selected{/if}{if $task.due_at|default:'' neq '' and $task.status neq 'done' and $task.due_at < $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'} is-overdue{/if}"
                            {if $selectedTask and $selectedTask.id eq $task.id} data-newly-selected-task="1"{/if}
                            draggable="{if $canWriteTaskboard}true{else}false{/if}"
                            data-task-id="{$task.id}"
                            data-board-id="{$selectedBoard.id}"
                            data-status="{$statusKey|escape}"
                            data-panel-url="{$baseUrl}?controller=taskboard&action=taskpanel&board_id={$selectedBoard.id}&task_id={$task.id}"
                            data-title="{$task.title|default:''|escape:'htmlall'} {$task.description|default:''|strip_tags|escape:'htmlall'}"
                            data-assigned-user-id="{$task.assigned_user_id|default:''|escape}"
                            data-loader-label="Ladowanie zadania {$task.title|escape:'htmlall'}"
                          >
                            {if $attachmentsByTaskId[$task.id]|default:false and $attachmentsByTaskId[$task.id][0].is_image|default:0}
                              <img src="./{$attachmentsByTaskId[$task.id][0].file_path|escape}" alt="{$task.title|escape}" class="taskboard-task-image">
                            {/if}
                            <div class="taskboard-task-topline">
                              <span class="taskboard-priority" style="background: {$priorityMeta.accent|escape};">{$priorityMeta.label|escape}</span>
                              {if $task.due_at|default:'' neq ''}
                                <span class="small fw-semibold {if $task.status neq 'done' and $task.due_at < $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'}text-danger{else}text-secondary{/if}">
                                  <i class="bi bi-alarm"></i> {$task.due_at|date_format:"%d-%m-%Y %H:%M"}
                                </span>
                              {/if}
                            </div>

                            <h5 class="taskboard-task-title">{$task.title|escape}</h5>
                            {if $task.description|default:'' neq ''}
                              <div class="small text-secondary mb-2">{$task.description|strip_tags|truncate:110|escape}</div>
                            {/if}
                            {if $notesByTaskId[$task.id]|default:false}
                              <div class="small text-secondary mb-2">
                                <i class="bi bi-chat-left-text"></i>
                                {$notesByTaskId[$task.id][0].note|strip_tags|truncate:80|escape}
                              </div>
                            {/if}

                            <div class="taskboard-task-bottomline">
                              <div class="d-flex flex-wrap align-items-center gap-2">
                                {if $subtasksByTaskId[$task.id]|@count > 0}<span class="badge text-bg-light"><i class="bi bi-check2-square"></i> {$subtasksByTaskId[$task.id]|@count}</span>{/if}
                                {if $attachmentsByTaskId[$task.id]|@count > 0}<span class="badge text-bg-light"><i class="bi bi-paperclip"></i> {$attachmentsByTaskId[$task.id]|@count}</span>{/if}
                              </div>
                              <div class="d-inline-flex align-items-center gap-2">
                                {if $task.assigned_user_id|default:0 > 0}
                                  <span class="taskboard-avatar" title="{if $task.assigned_user_first_name|default:'' neq '' or $task.assigned_user_last_name|default:'' neq ''}{$task.assigned_user_first_name|default:''|escape} {$task.assigned_user_last_name|default:''|escape}{else}{$task.assigned_user_email|escape}{/if}">
                                    {if $task.assigned_user_first_name|default:'' neq ''}
                                      {$task.assigned_user_first_name|truncate:1:''|upper|escape}{if $task.assigned_user_last_name|default:'' neq ''}{$task.assigned_user_last_name|truncate:1:''|upper|escape}{/if}
                                    {else}
                                      {$task.assigned_user_email|truncate:2:''|upper|escape}
                                    {/if}
                                  </span>
                                {else}
                                  <span class="badge text-bg-secondary">bez osoby</span>
                                {/if}
                                  <a
                                    class="btn btn-sm btn-outline-dark"
                                    href="{$baseUrl}?controller=taskboard&action=index&board_id={$selectedBoard.id}&task_id={$task.id}"
                                    data-taskboard-open
                                    data-task-id="{$task.id}"
                                    data-board-id="{$selectedBoard.id}"
                                    data-panel-url="{$baseUrl}?controller=taskboard&action=taskpanel&board_id={$selectedBoard.id}&task_id={$task.id}"
                                    onclick="return window.taskboardOpenTask ? window.taskboardOpenTask(this) : true;"
                                    title="Szczegoly zadania"
                                  >
                                    <i class="bi bi-eye"></i>
                                  </a>
                                {if $canWriteTaskboard}
                                  <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success"
                                    data-taskboard-done
                                    data-task-id="{$task.id}"
                                    data-board-id="{$selectedBoard.id}"
                                    title="Oznacz jako zrobione"
                                  >
                                    <i class="bi bi-check2"></i>
                                  </button>
                                {/if}
                              </div>
                            </div>
                          </article>
                        {/foreach}
                      {else}
                        <div class="taskboard-empty-state">
                          <i class="bi bi-inboxes"></i>
                          <div class="fw-semibold">Brak zadan w tej kolumnie</div>
                          <div class="small">Przeciagnij tutaj zadanie albo dodaj nowe.</div>
                        </div>
                      {/if}
                    </div>
                    </div>
                  </section>
                {/foreach}
              </div>
              <div class="taskboard-archive-shell">
                <div class="taskboard-archive-card">
                  <button
                    class="taskboard-archive-toggle"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#taskboardArchiveList"
                    aria-expanded="{if $openArchive|default:false}true{else}false{/if}"
                    aria-controls="taskboardArchiveList"
                  >
                    <span class="d-inline-flex align-items-center gap-2">
                      <i class="bi bi-archive"></i>
                      <span>Archiwum zadan</span>
                    </span>
                    <span class="badge text-bg-secondary">{$archivedTasks|@count}</span>
                  </button>
                  <div class="collapse{if $openArchive|default:false} show{/if}" id="taskboardArchiveList">
                    <div class="taskboard-archive-list">
                      {if $archivedTasks}
                        {foreach $archivedTasks as $task}
                          <div class="taskboard-archive-item text-decoration-none{if $selectedTask and $selectedTask.id eq $task.id} is-selected{/if}">
                            <div class="min-w-0">
                              <div class="fw-semibold text-dark">{$task.title|escape}</div>
                              <div class="small text-secondary">
                                {if $task.completed_at|default:'' neq ''}Zakonczone {$task.completed_at|date_format:"%d-%m-%Y %H:%M"}{/if}
                              </div>
                            </div>
                            <div class="taskboard-inline-actions">
                              {if $task.assigned_user_id|default:0 > 0}
                                <span class="taskboard-avatar -small">
                                  {if $task.assigned_user_first_name|default:'' neq ''}
                                    {$task.assigned_user_first_name|truncate:1:''|upper|escape}{if $task.assigned_user_last_name|default:'' neq ''}{$task.assigned_user_last_name|truncate:1:''|upper|escape}{/if}
                                  {else}
                                    {$task.assigned_user_email|truncate:2:''|upper|escape}
                                  {/if}
                                </span>
                              {/if}
                              <a
                                class="btn btn-sm btn-outline-dark"
                                href="{$baseUrl}?controller=taskboard&action=index&board_id={$selectedBoard.id}&task_id={$task.id}"
                                data-taskboard-open
                                data-task-id="{$task.id}"
                                data-board-id="{$selectedBoard.id}"
                                data-panel-url="{$baseUrl}?controller=taskboard&action=taskpanel&board_id={$selectedBoard.id}&task_id={$task.id}"
                                onclick="return window.taskboardOpenTask ? window.taskboardOpenTask(this) : true;"
                                title="Szczegoly zadania"
                              >
                                <i class="bi bi-eye"></i>
                              </a>
                              {if $canWriteTaskboard}
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-warning"
                                  data-taskboard-restore
                                  data-task-id="{$task.id}"
                                  data-board-id="{$selectedBoard.id}"
                                  title="Przywroc zadanie"
                                >
                                  <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                              {/if}
                            </div>
                          </div>
                        {/foreach}
                      {else}
                        <div class="small text-secondary px-2">Brak zadan w archiwum.</div>
                      {/if}
                    </div>
                  </div>
                </div>
              </div>
            {else}
              <div class="taskboard-sidebar-card">
                <div class="taskboard-empty-state">
                  <i class="bi bi-kanban"></i>
                  <div class="fw-semibold mb-2">Brak tablic do pokazania</div>
                  <div class="taskboard-empty-note">Utworz pierwsza tablice, zeby ruszyc z taskami, deadline'ami i praca zespolowa.</div>
                </div>
              </div>
            {/if}
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

{if $selectedBoard}
  <div class="offcanvas offcanvas-end taskboard-offcanvas" tabindex="-1" id="taskboardTaskDetails" aria-labelledby="taskboardTaskDetailsLabel" data-bs-backdrop="false" data-bs-scroll="true">
    <div class="offcanvas-header">
      <div>
        <h5 class="offcanvas-title mb-1" id="taskboardTaskDetailsLabel">{if $selectedTask}{$selectedTask.title|escape}{else}Szczegoly zadania{/if}</h5>
        <div class="small text-secondary" id="taskboardTaskDetailsMeta">{if $selectedTask}{$selectedBoard.name|escape}{else}Kliknij karte zadania{/if}</div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div id="taskboardTaskDetailsBody">
        {if $selectedTask}
          {include file='taskboard/task_details.tpl'}
        {else}
          <div class="taskboard-empty-state">
            <i class="bi bi-card-checklist"></i>
            <div class="fw-semibold mb-2">Kliknij zadanie</div>
            <div class="taskboard-empty-note">Szczegoly pojawia sie tutaj w bocznym panelu bez rozwalania drag and drop.</div>
          </div>
        {/if}
      </div>
    </div>
  </div>
{/if}

{if $selectedBoard and $canWriteTaskboard}
  <div class="offcanvas offcanvas-end taskboard-offcanvas" tabindex="-1" id="taskboardBoardSettings" aria-labelledby="taskboardBoardSettingsLabel" data-bs-backdrop="false" data-bs-scroll="true">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="taskboardBoardSettingsLabel">Ustawienia tablicy</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="taskboard-detail-card">
        <form method="post" action="{$baseUrl}?controller=taskboard&action=updateboard">
          <input type="hidden" name="board_id" value="{$selectedBoard.id}">
          <div class="mb-3">
            <label class="form-label" for="taskboardBoardName">Nazwa tablicy</label>
            <input type="text" id="taskboardBoardName" name="name" class="form-control" value="{$selectedBoard.name|escape}" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="taskboardBoardDescription">Opis</label>
            <textarea id="taskboardBoardDescription" name="description" class="form-control" rows="3">{$selectedBoard.description|default:''|escape}</textarea>
          </div>
          <div class="mb-3">
            <div class="form-label">Statusy tablicy</div>
            <div class="taskboard-status-builder" data-taskboard-status-builder="settings">
              {foreach $statusDefinitions as $statusKey => $statusMeta}
                <div class="taskboard-status-row">
                  <input type="text" name="status_labels[]" class="form-control" value="{$statusMeta.label|escape}" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>
                  <input type="color" name="status_accents[]" class="form-control form-control-color" value="{$statusMeta.accent|escape}" aria-label="Kolor statusu">
                  <select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">
                    {section name=span start=1 loop=13 step=1}
                      <option value="{$smarty.section.span.index}"{if $statusMeta.span|default:3 eq $smarty.section.span.index} selected{/if}>{$smarty.section.span.index}/12</option>
                    {/section}
                  </select>
                  <div class="taskboard-status-actions">
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>
                  </div>
                </div>
              {/foreach}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-taskboard-add-status="settings">Dodaj status</button>
            <div class="form-text">Mozesz ustawic wlasne kolumny, zmieniac ich kolejnosc i wybrac im kolory klikaniem.</div>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label" for="taskboardBoardAccent">Kolor akcentu</label>
              <input type="color" id="taskboardBoardAccent" name="accent_color" class="form-control form-control-color" value="{$selectedBoard.accent_color|default:'#0d6efd'|escape}">
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="taskboardArchiveToggle" name="is_archived" value="1"{if $selectedBoard.is_archived|default:0} checked{/if}>
                <label class="form-check-label" for="taskboardArchiveToggle">Archiwum</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-outline-dark w-100 mt-3">Zapisz tablice</button>
        </form>
        <form method="post" action="{$baseUrl}?controller=taskboard&action=deleteboard" class="mt-3" onsubmit="return confirm('Usunac cala tablice razem z zadaniami?');">
          <input type="hidden" name="board_id" value="{$selectedBoard.id}">
          <button type="submit" class="btn btn-outline-danger w-100">Usun tablice</button>
        </form>
      </div>
    </div>
  </div>
{/if}

{if $canWriteTaskboard}
  <div class="modal fade taskboard-modal" id="taskboardCreateBoardModal" tabindex="-1" aria-labelledby="taskboardCreateBoardModalLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="taskboardCreateBoardModalLabel">Nowa tablica</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" action="{$baseUrl}?controller=taskboard&action=createboard">
            <div class="mb-3">
              <label class="form-label" for="taskboardCreateBoardName">Nazwa</label>
              <input type="text" id="taskboardCreateBoardName" name="name" class="form-control" maxlength="120" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="taskboardCreateBoardDescription">Opis</label>
              <textarea id="taskboardCreateBoardDescription" name="description" class="form-control" rows="3" placeholder="Cel tablicy, proces, sprint..."></textarea>
            </div>
            <div class="mb-3">
              <div class="form-label">Statusy tablicy</div>
              <div class="taskboard-status-builder" data-taskboard-status-builder="create">
                <div class="taskboard-status-row">
                  <input type="text" name="status_labels[]" class="form-control" value="Do zrobienia" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>
                  <input type="color" name="status_accents[]" class="form-control form-control-color" value="#64748b" aria-label="Kolor statusu">
                  <select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">
                    {section name=span start=1 loop=13 step=1}
                      <option value="{$smarty.section.span.index}"{if $smarty.section.span.index eq 3} selected{/if}>{$smarty.section.span.index}/12</option>
                    {/section}
                  </select>
                  <div class="taskboard-status-actions">
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>
                  </div>
                </div>
                <div class="taskboard-status-row">
                  <input type="text" name="status_labels[]" class="form-control" value="W trakcie" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>
                  <input type="color" name="status_accents[]" class="form-control form-control-color" value="#0d6efd" aria-label="Kolor statusu">
                  <select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">
                    {section name=span start=1 loop=13 step=1}
                      <option value="{$smarty.section.span.index}"{if $smarty.section.span.index eq 3} selected{/if}>{$smarty.section.span.index}/12</option>
                    {/section}
                  </select>
                  <div class="taskboard-status-actions">
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>
                  </div>
                </div>
                <div class="taskboard-status-row">
                  <input type="text" name="status_labels[]" class="form-control" value="Do sprawdzenia" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>
                  <input type="color" name="status_accents[]" class="form-control form-control-color" value="#f59e0b" aria-label="Kolor statusu">
                  <select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">
                    {section name=span start=1 loop=13 step=1}
                      <option value="{$smarty.section.span.index}"{if $smarty.section.span.index eq 3} selected{/if}>{$smarty.section.span.index}/12</option>
                    {/section}
                  </select>
                  <div class="taskboard-status-actions">
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>
                  </div>
                </div>
                <div class="taskboard-status-row">
                  <input type="text" name="status_labels[]" class="form-control" value="Zrobione" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>
                  <input type="color" name="status_accents[]" class="form-control form-control-color" value="#10b981" aria-label="Kolor statusu">
                  <select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">
                    {section name=span start=1 loop=13 step=1}
                      <option value="{$smarty.section.span.index}"{if $smarty.section.span.index eq 3} selected{/if}>{$smarty.section.span.index}/12</option>
                    {/section}
                  </select>
                  <div class="taskboard-status-actions">
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-taskboard-add-status="create">Dodaj status</button>
              <div class="form-text">Mozesz od razu zrobic tylko swoje wlasne kolumny.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="taskboardCreateBoardAccent">Kolor akcentu</label>
              <input type="color" id="taskboardCreateBoardAccent" name="accent_color" class="form-control form-control-color" value="#0d6efd">
            </div>
            <button type="submit" class="btn btn-primary w-100">Utworz tablice</button>
          </form>
        </div>
      </div>
    </div>
  </div>
{/if}

{if $selectedBoard and $canWriteTaskboard}
  <div class="modal fade taskboard-modal" id="taskboardCreateTaskModal" tabindex="-1" aria-labelledby="taskboardCreateTaskModalLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="taskboardCreateTaskModalLabel">Nowe zadanie</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" action="{$baseUrl}?controller=taskboard&action=createtask" class="taskboard-form-surface">
            <input type="hidden" name="board_id" value="{$selectedBoard.id}">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="taskboardCreateTaskTitle">Tytul zadania</label>
                <input type="text" id="taskboardCreateTaskTitle" name="title" class="form-control" maxlength="190" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="taskboardCreateTaskDescription">Opis</label>
                <textarea id="taskboardCreateTaskDescription" name="description" class="form-control" rows="3" placeholder="Kontekst, kroki, warunki akceptacji..."></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="taskboardCreateTaskStatus">Status</label>
                <select id="taskboardCreateTaskStatus" name="status" class="form-select">
                  {foreach $activeStatusDefinitions as $statusKey => $statusMeta}
                    <option value="{$statusKey|escape}">{$statusMeta.label|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="taskboardCreateTaskPriority">Priorytet</label>
                <select id="taskboardCreateTaskPriority" name="priority" class="form-select">
                  {foreach $priorityDefinitions as $priorityKey => $priorityMeta}
                    <option value="{$priorityKey|escape}"{if $priorityKey eq 'medium'} selected{/if}>{$priorityMeta.label|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="taskboardCreateTaskAssignedUser">Osoba odpowiedzialna</label>
                <select id="taskboardCreateTaskAssignedUser" name="assigned_user_id" class="form-select">
                  <option value="">Bez przypisania</option>
                  {foreach $activeUsers as $user}
                    <option value="{$user.id}">{if $user.first_name|default:'' neq '' or $user.last_name|default:'' neq ''}{$user.first_name|default:''|escape} {$user.last_name|default:''|escape}{else}{$user.email|escape}{/if}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="taskboardCreateTaskDueAt">Termin wykonania</label>
                <input type="datetime-local" id="taskboardCreateTaskDueAt" name="due_at" class="form-control">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Dodaj zadanie</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
{/if}

<script>
  (function () {
    var columns = document.querySelectorAll('.taskboard-stage-body');
    var cards = document.querySelectorAll('.taskboard-task-card');
    var draggedCard = null;
    var hasDraggedCard = false;
    var movingRequest = null;
    var activeAssigneeFilter = '';
    var taskPanel = document.getElementById('taskboardTaskDetails');
    var taskPanelBody = document.getElementById('taskboardTaskDetailsBody');
    var taskPanelTitle = document.getElementById('taskboardTaskDetailsLabel');
    var taskPanelMeta = document.getElementById('taskboardTaskDetailsMeta');
    var createBoardModal = document.getElementById('taskboardCreateBoardModal');
    var createTaskModal = document.getElementById('taskboardCreateTaskModal');
    var boardSettingsPanel = document.getElementById('taskboardBoardSettings');
    var taskPanelInstance = null;
    var createBoardModalInstance = null;
    var autosaveTimer = null;
    var autosaveRequest = null;

    function getTaskPanelInstance() {
      if (!taskPanel || !window.bootstrap || !window.bootstrap.Offcanvas) {
        return null;
      }

      if (!taskPanelInstance) {
        taskPanelInstance = window.bootstrap.Offcanvas.getOrCreateInstance(taskPanel);
      }

      return taskPanelInstance;
    }

    function getCreateBoardModalInstance() {
      if (!createBoardModal || !window.bootstrap || !window.bootstrap.Modal) {
        return null;
      }

      if (!createBoardModalInstance) {
        createBoardModalInstance = window.bootstrap.Modal.getOrCreateInstance(createBoardModal);
      }

      return createBoardModalInstance;
    }

    function closeBootstrapLayer(element) {
      if (!element || !window.bootstrap) {
        return;
      }

      if (element.classList.contains('modal') && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(element).hide();
        return;
      }

      if (element.classList.contains('offcanvas') && window.bootstrap.Offcanvas) {
        window.bootstrap.Offcanvas.getOrCreateInstance(element).hide();
      }
    }

    function updateTaskboardEditLayerState() {
      var editLayerOpen = false;
      var editLayerSelectors = ['#taskboardBoardSettings', '#taskboardCreateBoardModal', '#taskboardCreateTaskModal'];
      for (var i = 0; i < editLayerSelectors.length; i++) {
        var layer = document.querySelector(editLayerSelectors[i]);
        if (layer && layer.classList.contains('show')) {
          editLayerOpen = true;
          break;
        }
      }

      document.body.classList.toggle('taskboard-edit-layer-active', editLayerOpen);
    }

    function bindTaskboardEditLayers() {
      var layers = [boardSettingsPanel, createBoardModal, createTaskModal];
      for (var i = 0; i < layers.length; i++) {
        if (!layers[i]) {
          continue;
        }

        layers[i].addEventListener('shown.bs.modal', updateTaskboardEditLayerState);
        layers[i].addEventListener('hidden.bs.modal', updateTaskboardEditLayerState);
        layers[i].addEventListener('shown.bs.offcanvas', updateTaskboardEditLayerState);
        layers[i].addEventListener('hidden.bs.offcanvas', updateTaskboardEditLayerState);
      }
    }

    function bindOutsideCloseForEditLayers() {
      document.addEventListener('click', function (event) {
        var openLayers = [boardSettingsPanel, createBoardModal, createTaskModal];
        for (var i = 0; i < openLayers.length; i++) {
          var layer = openLayers[i];
          if (!layer || !layer.classList.contains('show')) {
            continue;
          }

          var contentSelector = layer.classList.contains('modal') ? '.modal-dialog' : '.offcanvas-body, .offcanvas-header';
          if (event.target.closest(contentSelector)) {
            continue;
          }

          if (event.target.closest('[data-bs-target="#' + layer.id + '"]')) {
            continue;
          }

          closeBootstrapLayer(layer);
        }
      });
    }

    function animateTaskDetails(root) {
      var detailCard = root ? root.querySelector('.taskboard-detail-card') : null;
      if (!detailCard) {
        return;
      }

      detailCard.classList.remove('is-animated');
      void detailCard.offsetWidth;
      detailCard.classList.add('is-animated');
    }

    function animateSelectedTaskCard() {
      var selectedCard = document.querySelector('.taskboard-task-card[data-newly-selected-task="1"]');
      if (!selectedCard) {
        return;
      }

      selectedCard.classList.add('is-newly-created', 'is-panel-target');
      var columnsScroller = document.getElementById('taskboardColumns');
      if (columnsScroller && typeof columnsScroller.scrollTo === 'function') {
        var cardRect = selectedCard.getBoundingClientRect();
        var scrollerRect = columnsScroller.getBoundingClientRect();
        var cardCenter = cardRect.left - scrollerRect.left + columnsScroller.scrollLeft + (cardRect.width / 2);
        var targetScrollLeft = Math.max(0, cardCenter - (scrollerRect.width / 2));

        columnsScroller.scrollTo({
          left: targetScrollLeft,
          behavior: 'smooth'
        });
      }

      window.setTimeout(function () {
        selectedCard.classList.remove('is-newly-created');
      }, 1100);
      window.setTimeout(function () {
        selectedCard.classList.remove('is-panel-target');
      }, 2400);
    }

    function serializeColumn(column) {
      var ids = [];
      var columnCards = column.querySelectorAll('.taskboard-task-card');
      for (var i = 0; i < columnCards.length; i++) {
        ids.push(columnCards[i].getAttribute('data-task-id'));
      }
      return ids;
    }

    function buildEmptyState() {
      var wrap = document.createElement('div');
      wrap.className = 'taskboard-empty-state';
      wrap.innerHTML = '<i class="bi bi-inboxes"></i><div class="fw-semibold">Brak zadan w tej kolumnie</div><div class="small">Przeciagnij tutaj zadanie albo dodaj nowe.</div>';
      return wrap;
    }

    function refreshColumnEmptyStates() {
      for (var i = 0; i < columns.length; i++) {
        var column = columns[i];
        var cardsInColumn = column.querySelectorAll('.taskboard-task-card');
        var visibleCards = 0;
        var emptyState = column.querySelector('.taskboard-empty-state');
        for (var c = 0; c < cardsInColumn.length; c++) {
          if (cardsInColumn[c].style.display !== 'none') {
            visibleCards++;
          }
        }

        if (visibleCards === 0) {
          if (!emptyState) {
            column.appendChild(buildEmptyState());
          }
        } else if (emptyState) {
          emptyState.remove();
        }
      }
    }

    function postTaskMove(card, targetColumn) {
      if (!card || !targetColumn) {
        return;
      }

      var boardId = targetColumn.getAttribute('data-board-id');
      var status = targetColumn.getAttribute('data-status');
      var taskId = card.getAttribute('data-task-id');
      var formData = new FormData();
      var orderedIds = serializeColumn(targetColumn);

      formData.append('board_id', boardId || '');
      formData.append('status', status || 'todo');
      formData.append('task_id', taskId || '');
      for (var i = 0; i < orderedIds.length; i++) {
        formData.append('ordered_ids[]', orderedIds[i]);
      }

      if (movingRequest && typeof movingRequest.abort === 'function') {
        movingRequest.abort();
      }

      movingRequest = new AbortController();

      fetch('{$baseUrl|escape:'javascript'}?controller=taskboard&action=movetask', {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        },
        signal: movingRequest.signal
      }).then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie zapisac pozycji zadania.'));
      }).then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie zapisac pozycji zadania.');
        }

        refreshColumnEmptyStates();
        targetColumn.classList.add('taskboard-pulse');
        window.setTimeout(function () {
          targetColumn.classList.remove('taskboard-pulse');
        }, 260);
      }).catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }

        window.alert(error && error.message ? error.message : 'Nie udalo sie przeniesc zadania.');
        window.location.reload();
      });
    }

    function attachDragEvents(card) {
      card.addEventListener('dragstart', function () {
        draggedCard = card;
        hasDraggedCard = true;
        card.classList.add('is-dragging');
      });

      card.addEventListener('dragend', function () {
        card.classList.remove('is-dragging');
        draggedCard = null;
        window.setTimeout(function () {
          hasDraggedCard = false;
        }, 0);
        for (var i = 0; i < columns.length; i++) {
          columns[i].classList.remove('is-drag-target');
        }
        refreshColumnEmptyStates();
      });
    }

    function bindSubtaskCheckboxes(root) {
      var scope = root || document;
      var subtaskCheckboxes = scope.querySelectorAll('.taskboard-subtask-checkbox[data-subtask-id]');
      for (var k = 0; k < subtaskCheckboxes.length; k++) {
        if (subtaskCheckboxes[k].getAttribute('data-taskboard-bound') === '1') {
          continue;
        }

        subtaskCheckboxes[k].setAttribute('data-taskboard-bound', '1');
        subtaskCheckboxes[k].addEventListener('change', function () {
          var checkbox = this;
          var subtaskId = checkbox.getAttribute('data-subtask-id');
          var row = checkbox.closest('[data-subtask-row]');
          var formData = new FormData();
          formData.append('subtask_id', subtaskId || '');

          fetch('{$baseUrl|escape:'javascript'}?controller=taskboard&action=togglesubtask', {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json'
            }
          }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie zmienic statusu podzadania.'));
          }).then(function (payload) {
            if (!payload || payload.ok !== true) {
              throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie zmienic statusu podzadania.');
            }

            checkbox.checked = payload.is_done === true;
            if (row) {
              row.classList.toggle('is-done', payload.is_done === true);
              row.classList.add('taskboard-pulse');
              window.setTimeout(function () {
                row.classList.remove('taskboard-pulse');
              }, 260);
            }
            playTaskboardTone(payload.is_done === true);
          }).catch(function (error) {
            checkbox.checked = !checkbox.checked;
            window.alert(error && error.message ? error.message : 'Nie udalo sie zmienic statusu podzadania.');
          });
        });
      }
    }

    function bindPasteUpload() {
      document.addEventListener('paste', function (event) {
        if (!taskPanel || !taskPanel.classList.contains('show') || !taskPanelBody) {
          return;
        }

        var detailRoot = taskPanelBody.querySelector('[data-task-detail-root]');
        if (!detailRoot) {
          return;
        }

        var clipboardItems = event.clipboardData && event.clipboardData.items ? event.clipboardData.items : [];
        if (!clipboardItems || clipboardItems.length === 0) {
          return;
        }

        var imageFile = null;
        for (var i = 0; i < clipboardItems.length; i++) {
          if (clipboardItems[i].type && clipboardItems[i].type.indexOf('image/') === 0) {
            imageFile = clipboardItems[i].getAsFile();
            break;
          }
        }

        if (!imageFile) {
          return;
        }

        event.preventDefault();
        var boardId = detailRoot.getAttribute('data-board-id');
        var taskId = detailRoot.getAttribute('data-task-id');
        var formData = new FormData();
        formData.append('board_id', boardId || '');
        formData.append('task_id', taskId || '');
        formData.append('ajax', '1');
        formData.append('attachment', imageFile, 'clipboard-image.png');

        fetch('{$baseUrl|escape:'javascript'}?controller=taskboard&action=uploadattachment', {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json'
          }
        }).then(function (response) {
          return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie wkleic obrazu.'));
        }).then(function (payload) {
          if (!payload || payload.ok !== true) {
            throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie wkleic obrazu.');
          }

          refreshTaskPanel(boardId, taskId);
        }).catch(function (error) {
          window.alert(error && error.message ? error.message : 'Nie udalo sie wkleic obrazu.');
        });
      });
    }

    function markPanelUpdated() {
      if (!taskPanelBody) {
        return;
      }

      taskPanelBody.classList.remove('just-updated');
      window.setTimeout(function () {
        taskPanelBody.classList.add('just-updated');
        window.setTimeout(function () {
          taskPanelBody.classList.remove('just-updated');
        }, 620);
      }, 0);
    }

    function openTaskPanel(panelUrl, taskId, boardId, silent) {
      if (!panelUrl || !taskPanelBody) {
        return;
      }

      var panelBodyScroll = taskPanelBody.parentElement ? taskPanelBody.parentElement.scrollTop : 0;
      if (silent && taskPanelBody.children.length > 0) {
        taskPanelBody.classList.add('is-refreshing');
      } else {
        taskPanelBody.innerHTML = '<div class="py-4 text-center text-secondary">Ladowanie szczegolow zadania...</div>';
      }

      fetch(panelUrl, {
        headers: {
          'Accept': 'application/json'
        }
      }).then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie pobrac szczegolow zadania.'));
      }).then(function (payload) {
        if (!payload || payload.ok !== true || !payload.html) {
          throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie pobrac szczegolow zadania.');
        }

        taskPanelBody.innerHTML = payload.html;
        taskPanelBody.classList.remove('is-refreshing');
        if (taskPanelTitle) {
          taskPanelTitle.textContent = payload.title || 'Szczegoly zadania';
        }
        bindSubtaskCheckboxes(taskPanelBody);
        bindTaskDetailAutosave(taskPanelBody);
        bindSubtaskCreateForms(taskPanelBody);
        bindNoteCreateForms(taskPanelBody);
        bindRefreshPanelForms(taskPanelBody);
        animateTaskDetails(taskPanelBody);
        if (silent) {
          window.requestAnimationFrame(function () {
            if (taskPanelBody.parentElement) {
              taskPanelBody.parentElement.scrollTop = panelBodyScroll;
            }
            markPanelUpdated();
          });
        }

        var panelInstance = getTaskPanelInstance();
        if (panelInstance && !silent) {
          panelInstance.show();
        } else if (panelInstance && silent && !taskPanel.classList.contains('show')) {
          panelInstance.show();
        }

        if (window.history && typeof window.history.replaceState === 'function' && boardId && taskId) {
          window.history.replaceState({}, '', '{$baseUrl|escape:'javascript'}?controller=taskboard&action=index&board_id=' + encodeURIComponent(boardId) + '&task_id=' + encodeURIComponent(taskId));
        }
      }).catch(function (error) {
        taskPanelBody.classList.remove('is-refreshing');
        taskPanelBody.innerHTML = '<div class="alert alert-danger mb-0">' + String(error && error.message ? error.message : 'Nie udalo sie pobrac szczegolow zadania.') + '</div>';
      });
    }

    window.taskboardOpenTask = function (trigger) {
      if (!trigger) {
        return false;
      }

      var panelUrl = trigger.getAttribute('data-panel-url');
      var taskId = trigger.getAttribute('data-task-id');
      var boardId = trigger.getAttribute('data-board-id');
      if (!panelUrl || !taskPanelBody) {
        return true;
      }

      openTaskPanel(panelUrl, taskId, boardId);
      return false;
    };

    document.addEventListener('click', function (event) {
      if (!taskPanel || !taskPanel.classList.contains('show')) {
        return;
      }

      if (event.target.closest('#taskboardTaskDetails')) {
        return;
      }

      if (event.target.closest('.taskboard-task-card, [data-taskboard-open], a, button, input, select, textarea, label, .modal.show, .offcanvas.show')) {
        return;
      }

      var panelInstance = getTaskPanelInstance();
      if (panelInstance) {
        panelInstance.hide();
      }
    });

    function findDropTarget(column, clientY) {
      var columnCards = column.querySelectorAll('.taskboard-task-card:not(.is-dragging)');
      var closest = null;
      var closestOffset = Number.NEGATIVE_INFINITY;

      for (var i = 0; i < columnCards.length; i++) {
        var box = columnCards[i].getBoundingClientRect();
        var offset = clientY - box.top - (box.height / 2);
        if (offset < 0 && offset > closestOffset) {
          closestOffset = offset;
          closest = columnCards[i];
        }
      }

      return closest;
    }

    for (var i = 0; i < cards.length; i++) {
      attachDragEvents(cards[i]);
    }

    for (var j = 0; j < columns.length; j++) {
      columns[j].addEventListener('dragover', function (event) {
        if (!draggedCard) {
          return;
        }

        event.preventDefault();
        this.classList.add('is-drag-target');
        var afterElement = findDropTarget(this, event.clientY);
        if (afterElement) {
          this.insertBefore(draggedCard, afterElement);
        } else {
          this.appendChild(draggedCard);
        }
      });

      columns[j].addEventListener('dragleave', function (event) {
        if (this.contains(event.relatedTarget)) {
          return;
        }

        this.classList.remove('is-drag-target');
      });

      columns[j].addEventListener('drop', function (event) {
        if (!draggedCard) {
          return;
        }

        event.preventDefault();
        this.classList.remove('is-drag-target');
        refreshColumnEmptyStates();
        postTaskMove(draggedCard, this);
      });
    }

    function applyFilters() {
      var assigned = String(activeAssigneeFilter || '');
      var taskCards = document.querySelectorAll('.taskboard-task-card');

      for (var i = 0; i < taskCards.length; i++) {
        var taskCard = taskCards[i];
        var assignedUserId = String(taskCard.getAttribute('data-assigned-user-id') || '');
        var visibleByAssignee = true;

        if (assigned === 'unassigned') {
          visibleByAssignee = assignedUserId === '' || assignedUserId === '0';
        } else if (assigned !== '') {
          visibleByAssignee = assignedUserId === assigned;
        }

        taskCard.style.display = visibleByAssignee ? '' : 'none';
      }

      refreshColumnEmptyStates();
    }

    function setActiveFilterButton(value) {
      var buttons = document.querySelectorAll('[data-taskboard-assignee-filter]');
      for (var i = 0; i < buttons.length; i++) {
        var button = buttons[i];
        var isActive = String(button.getAttribute('data-taskboard-assignee-filter') || '') === String(value || '');
        button.classList.toggle('btn-primary', isActive);
        button.classList.toggle('btn-outline-secondary', !isActive);
      }
    }

    document.addEventListener('click', function (event) {
      var filterButton = event.target.closest('[data-taskboard-assignee-filter]');
      if (filterButton) {
        event.preventDefault();
        activeAssigneeFilter = String(filterButton.getAttribute('data-taskboard-assignee-filter') || '');
        setActiveFilterButton(activeAssigneeFilter);
        applyFilters();
      }
    });

    function setAutosaveStatus(root, message, state) {
      var status = root ? root.querySelector('[data-taskboard-autosave-status]') : null;
      if (!status) {
        return;
      }

      status.textContent = message || 'Zapis automatyczny';
      status.classList.toggle('text-success', state === 'success');
      status.classList.toggle('text-danger', state === 'error');
      status.classList.toggle('text-secondary', !state);
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatMultilineText(value) {
      return escapeHtml(value).replace(/\r\n|\r|\n/g, '<br>');
    }

    function updateDetailCount(root, selector, nextValue) {
      var badge = root ? root.querySelector(selector) : null;
      if (!badge) {
        return;
      }

      badge.textContent = String(Math.max(0, Number(nextValue) || 0));
    }

    function appendSubtaskToPanel(form, payload, label) {
      var root = form.closest('[data-task-detail-root]');
      var checklist = root ? root.querySelector('[data-taskboard-checklist]') : null;
      if (!root || !checklist) {
        refreshTaskPanel(payload.board_id, payload.task_id);
        return;
      }

      var subtaskId = String(payload.subtask_id || '');
      if (subtaskId === '') {
        refreshTaskPanel(payload.board_id, payload.task_id);
        return;
      }

      var emptyState = checklist.querySelector('[data-taskboard-empty-subtasks]');
      if (emptyState) {
        emptyState.remove();
      }

      var row = document.createElement('div');
      row.className = 'taskboard-subtask-row';
      row.setAttribute('data-subtask-row', subtaskId);
      row.innerHTML = ''
        + '<input type="checkbox" id="taskboardSubtask' + escapeHtml(subtaskId) + '" class="taskboard-subtask-checkbox" data-subtask-id="' + escapeHtml(subtaskId) + '">'
        + '<label for="taskboardSubtask' + escapeHtml(subtaskId) + '" class="taskboard-subtask-label flex-grow-1 mb-0">' + escapeHtml(label) + '</label>'
        + '<button type="submit" class="btn btn-sm btn-outline-danger taskboard-item-delete" form="taskboard-subtask-delete-' + escapeHtml(subtaskId) + '" title="Usun podzadanie">'
        + '<i class="bi bi-trash"></i>'
        + '</button>';
      checklist.appendChild(row);

      var deleteForm = document.createElement('form');
      deleteForm.method = 'post';
      deleteForm.action = '{$baseUrl|escape:'javascript'}?controller=taskboard&action=deletesubtask';
      deleteForm.id = 'taskboard-subtask-delete-' + subtaskId;
      deleteForm.setAttribute('data-taskboard-refresh-panel-form', '');
      deleteForm.setAttribute('data-confirm-message', 'Usunac to podzadanie?');
      deleteForm.innerHTML = ''
        + '<input type="hidden" name="board_id" value="' + escapeHtml(payload.board_id || '') + '">'
        + '<input type="hidden" name="task_id" value="' + escapeHtml(payload.task_id || '') + '">'
        + '<input type="hidden" name="subtask_id" value="' + escapeHtml(subtaskId) + '">'
        + '<input type="hidden" name="ajax" value="1">';
      root.appendChild(deleteForm);

      updateDetailCount(root, '[data-taskboard-subtask-count]', checklist.querySelectorAll('[data-subtask-row]').length);
      bindSubtaskCheckboxes(row);
      bindRefreshPanelForms(root);
      markPanelUpdated();
    }

    function appendNoteToPanel(form, payload, noteText) {
      var root = form.closest('[data-task-detail-root]');
      var noteList = root ? root.querySelector('[data-taskboard-note-list]') : null;
      if (!root || !noteList) {
        refreshTaskPanel(payload.board_id, payload.task_id);
        return;
      }

      var noteId = String(payload.note_id || '');
      if (noteId === '') {
        refreshTaskPanel(payload.board_id, payload.task_id);
        return;
      }

      var emptyState = noteList.querySelector('[data-taskboard-empty-notes]');
      if (emptyState) {
        emptyState.remove();
      }

      var noteItem = document.createElement('div');
      noteItem.className = 'taskboard-note-item';
      noteItem.innerHTML = ''
        + '<div class="taskboard-note-meta small mb-1 d-flex justify-content-between gap-2">'
        + '<span>' + escapeHtml(payload.author || 'System') + '</span>'
        + '<span class="d-inline-flex align-items-center gap-2">'
        + escapeHtml(payload.created_at || '')
        + '<button type="submit" class="btn btn-sm btn-outline-danger taskboard-item-delete" form="taskboard-note-delete-' + escapeHtml(noteId) + '" title="Usun notatke">'
        + '<i class="bi bi-trash"></i>'
        + '</button>'
        + '</span>'
        + '</div>'
        + '<div class="small">' + formatMultilineText(noteText) + '</div>';
      noteList.insertBefore(noteItem, noteList.firstChild);

      var deleteForm = document.createElement('form');
      deleteForm.method = 'post';
      deleteForm.action = '{$baseUrl|escape:'javascript'}?controller=taskboard&action=deletenote';
      deleteForm.id = 'taskboard-note-delete-' + noteId;
      deleteForm.setAttribute('data-taskboard-refresh-panel-form', '');
      deleteForm.setAttribute('data-confirm-message', 'Usunac te notatke?');
      deleteForm.innerHTML = ''
        + '<input type="hidden" name="board_id" value="' + escapeHtml(payload.board_id || '') + '">'
        + '<input type="hidden" name="task_id" value="' + escapeHtml(payload.task_id || '') + '">'
        + '<input type="hidden" name="note_id" value="' + escapeHtml(noteId) + '">'
        + '<input type="hidden" name="ajax" value="1">';
      root.appendChild(deleteForm);

      updateDetailCount(root, '[data-taskboard-note-count]', noteList.querySelectorAll('.taskboard-note-item').length);
      bindRefreshPanelForms(root);
      markPanelUpdated();
    }

    function saveTaskDetails(form) {
      if (!form) {
        return;
      }

      var root = form.closest('[data-task-detail-root]');
      var formData = new FormData(form);
      formData.append('ajax', '1');
      setAutosaveStatus(root, 'Zapisywanie...', null);

      if (autosaveRequest && typeof autosaveRequest.abort === 'function') {
        autosaveRequest.abort();
      }

      autosaveRequest = new AbortController();
      fetch(form.getAttribute('action'), {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        },
        signal: autosaveRequest.signal
      }).then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie zapisac zadania.'));
      }).then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie zapisac zadania.');
        }

        setAutosaveStatus(root, 'Zapisano automatycznie', 'success');
        if (payload.title) {
          if (taskPanelTitle) {
            taskPanelTitle.textContent = payload.title;
          }
          var currentTaskId = payload.task_id || (root ? root.getAttribute('data-task-id') : '');
          var taskCard = currentTaskId ? document.querySelector('.taskboard-task-card[data-task-id="' + String(currentTaskId) + '"]') : null;
          var taskCardTitle = taskCard ? taskCard.querySelector('.taskboard-task-title') : null;
          if (taskCardTitle) {
            taskCardTitle.textContent = payload.title;
          }
        }
      }).catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }

        setAutosaveStatus(root, error && error.message ? error.message : 'Nie udalo sie zapisac zadania.', 'error');
      });
    }

    function bindTaskDetailAutosave(root) {
      var scope = root || document;
      var forms = scope.querySelectorAll('[data-taskboard-autosave-form]');
      for (var i = 0; i < forms.length; i++) {
        if (forms[i].getAttribute('data-taskboard-bound') === '1') {
          continue;
        }

        forms[i].setAttribute('data-taskboard-bound', '1');
        forms[i].addEventListener('submit', function (event) {
          event.preventDefault();
          saveTaskDetails(this);
        });
        forms[i].addEventListener('change', function (event) {
          var targetFormId = event.target ? event.target.getAttribute('form') : '';
          if (targetFormId && targetFormId !== this.id) {
            return;
          }

          saveTaskDetails(this);
        });
        forms[i].addEventListener('input', function (event) {
          if (!event.target || (event.target.tagName !== 'TEXTAREA' && !event.target.hasAttribute('data-task-title-input'))) {
            return;
          }

          var targetFormId = event.target.getAttribute('form');
          if (targetFormId && targetFormId !== this.id) {
            return;
          }

          var form = this;
          if (autosaveTimer) {
            window.clearTimeout(autosaveTimer);
          }
          autosaveTimer = window.setTimeout(function () {
            saveTaskDetails(form);
          }, 500);
        });
      }
    }

    function refreshTaskPanel(boardId, taskId) {
      if (!boardId || !taskId) {
        return;
      }

      openTaskPanel('{$baseUrl|escape:'javascript'}?controller=taskboard&action=taskpanel&board_id=' + encodeURIComponent(boardId) + '&task_id=' + encodeURIComponent(taskId), taskId, boardId, true);
    }

    function bindRefreshPanelForms(root) {
      var scope = root || document;
      var forms = scope.querySelectorAll('[data-taskboard-refresh-panel-form]:not(form[action*="action=addnote"])');
      for (var i = 0; i < forms.length; i++) {
        if (forms[i].getAttribute('data-taskboard-bound') === '1') {
          continue;
        }

        forms[i].setAttribute('data-taskboard-bound', '1');
        forms[i].addEventListener('submit', function (event) {
          event.preventDefault();

          var form = this;
          var message = form.getAttribute('data-confirm-message');
          if (message && !window.confirm(message)) {
            return;
          }

          var submitButton = document.activeElement && document.activeElement.getAttribute('form') === form.id ? document.activeElement : form.querySelector('[type="submit"]');
          var formData = new FormData(form);
          formData.append('ajax', '1');
          if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('disabled');
          }

          fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json'
            }
          }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie wykonac operacji.'));
          }).then(function (payload) {
            if (!payload || payload.ok !== true) {
              throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie wykonac operacji.');
            }

            refreshTaskPanel(payload.board_id || formData.get('board_id'), payload.task_id || formData.get('task_id'));
          }).catch(function (error) {
            window.alert(error && error.message ? error.message : 'Nie udalo sie wykonac operacji.');
          }).finally(function () {
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.classList.remove('disabled');
            }
          });
        });
      }
    }

    function bindSubtaskCreateForms(root) {
      var scope = root || document;
      var forms = scope.querySelectorAll('[data-taskboard-subtask-form]');
      for (var i = 0; i < forms.length; i++) {
        if (forms[i].getAttribute('data-taskboard-bound') === '1') {
          continue;
        }

        forms[i].setAttribute('data-taskboard-bound', '1');
        forms[i].addEventListener('submit', function (event) {
          event.preventDefault();

          var form = this;
          var labelInput = document.querySelector('[form="' + form.id + '"][name="label"]');
          var formData = new FormData(form);
          var labelValue = labelInput ? String(labelInput.value || '').trim() : '';
          if (labelInput) {
            formData.append('label', labelInput.value || '');
          }
          formData.append('ajax', '1');

          fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json'
            }
          }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie dodac podzadania.'));
          }).then(function (payload) {
            if (!payload || payload.ok !== true) {
              throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie dodac podzadania.');
            }

            if (labelInput) {
              labelInput.value = '';
            }
            appendSubtaskToPanel(form, payload, labelValue);
          }).catch(function (error) {
            window.alert(error && error.message ? error.message : 'Nie udalo sie dodac podzadania.');
          });
        });
      }
    }

    function bindNoteCreateForms(root) {
      var scope = root || document;
      var forms = scope.querySelectorAll('form[action*="action=addnote"][data-taskboard-refresh-panel-form]');
      for (var i = 0; i < forms.length; i++) {
        if (forms[i].getAttribute('data-taskboard-note-create-bound') === '1') {
          continue;
        }

        forms[i].setAttribute('data-taskboard-note-create-bound', '1');
        forms[i].addEventListener('submit', function (event) {
          event.preventDefault();

          var form = this;
          var noteInput = document.querySelector('[form="' + form.id + '"][name="note"]');
          var noteValue = noteInput ? String(noteInput.value || '').trim() : '';
          var formData = new FormData(form);
          if (noteInput) {
            formData.append('note', noteInput.value || '');
          }
          formData.append('ajax', '1');

          fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json'
            }
          }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie dodac notatki.'));
          }).then(function (payload) {
            if (!payload || payload.ok !== true) {
              throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie dodac notatki.');
            }

            if (noteInput) {
              noteInput.value = '';
            }
            appendNoteToPanel(form, payload, noteValue);
          }).catch(function (error) {
            window.alert(error && error.message ? error.message : 'Nie udalo sie dodac notatki.');
          });
        });
      }
    }

    function bindStatusBuilder() {
      function createStatusRow(label, color) {
        var row = document.createElement('div');
        row.className = 'taskboard-status-row';
        row.innerHTML = ''
          + '<input type="text" name="status_labels[]" class="form-control" placeholder="Nazwa statusu" aria-label="Nazwa statusu" required>'
          + '<input type="color" name="status_accents[]" class="form-control form-control-color" value="#0d6efd" aria-label="Kolor statusu">'
          + '<select name="status_spans[]" class="form-select" aria-label="Szerokosc statusu">'
          + '<option value="1">1/12</option><option value="2">2/12</option><option value="3" selected>3/12</option><option value="4">4/12</option><option value="5">5/12</option><option value="6">6/12</option><option value="7">7/12</option><option value="8">8/12</option><option value="9">9/12</option><option value="10">10/12</option><option value="11">11/12</option><option value="12">12/12</option>'
          + '</select>'
          + '<div class="taskboard-status-actions">'
          + '<button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="up" title="Przenies wyzej"><i class="bi bi-arrow-up"></i></button>'
          + '<button type="button" class="btn btn-outline-secondary" data-taskboard-move-status="down" title="Przenies nizej"><i class="bi bi-arrow-down"></i></button>'
          + '<button type="button" class="btn btn-outline-danger" data-taskboard-remove-status title="Usun status">&times;</button>'
          + '</div>';
        row.querySelector('input[name="status_labels[]"]').value = label || '';
        row.querySelector('input[name="status_accents[]"]').value = color || '#0d6efd';
        return row;
      }

      document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-taskboard-add-status]');
        if (addButton) {
          var key = addButton.getAttribute('data-taskboard-add-status');
          var builder = document.querySelector('[data-taskboard-status-builder="' + key + '"]');
          if (builder) {
            builder.appendChild(createStatusRow('', '#0d6efd'));
          }
          return;
        }

        var removeButton = event.target.closest('[data-taskboard-remove-status]');
        if (removeButton) {
          var currentRow = removeButton.closest('.taskboard-status-row');
          var currentBuilder = currentRow ? currentRow.parentElement : null;
          if (!currentRow || !currentBuilder) {
            return;
          }

          if (currentBuilder.querySelectorAll('.taskboard-status-row').length <= 1) {
            return;
          }

          currentRow.remove();
          return;
        }

        var moveButton = event.target.closest('[data-taskboard-move-status]');
        if (!moveButton) {
          return;
        }

        var rowToMove = moveButton.closest('.taskboard-status-row');
        var direction = moveButton.getAttribute('data-taskboard-move-status');
        if (!rowToMove || !rowToMove.parentElement) {
          return;
        }

        if (direction === 'up' && rowToMove.previousElementSibling) {
          rowToMove.parentElement.insertBefore(rowToMove, rowToMove.previousElementSibling);
          return;
        }

        if (direction === 'down' && rowToMove.nextElementSibling) {
          rowToMove.parentElement.insertBefore(rowToMove.nextElementSibling, rowToMove);
        }
      });
    }

    document.addEventListener('click', function (event) {
      var doneButton = event.target.closest('[data-taskboard-done]');
      if (doneButton) {
        event.preventDefault();
        event.stopPropagation();

        var doneFormData = new FormData();
        var doneBoardId = doneButton.getAttribute('data-board-id') || '';
        var doneTaskId = doneButton.getAttribute('data-task-id') || '';
        doneFormData.append('board_id', doneBoardId);
        doneFormData.append('task_id', doneTaskId);
        doneFormData.append('ajax', '1');

        fetch('{$baseUrl|escape:'javascript'}?controller=taskboard&action=marktaskdone', {
          method: 'POST',
          body: doneFormData,
          headers: {
            'Accept': 'application/json'
          }
        }).then(function (response) {
          return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie oznaczyc zadania jako zrobione.'));
        }).then(function (payload) {
          if (!payload || payload.ok !== true) {
            throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie oznaczyc zadania jako zrobione.');
          }

          window.location.href = '{$baseUrl|escape:'javascript'}?controller=taskboard&action=index&board_id=' + encodeURIComponent(doneBoardId) + '&archive=1';
        }).catch(function (error) {
          window.alert(error && error.message ? error.message : 'Nie udalo sie oznaczyc zadania jako zrobione.');
        });
        return;
      }

      var restoreButton = event.target.closest('[data-taskboard-restore]');
      if (restoreButton) {
        event.preventDefault();
        event.stopPropagation();

        var restoreFormData = new FormData();
        var restoreBoardId = restoreButton.getAttribute('data-board-id') || '';
        var restoreTaskId = restoreButton.getAttribute('data-task-id') || '';
        restoreFormData.append('board_id', restoreBoardId);
        restoreFormData.append('task_id', restoreTaskId);
        restoreFormData.append('ajax', '1');

        fetch('{$baseUrl|escape:'javascript'}?controller=taskboard&action=restoretask', {
          method: 'POST',
          body: restoreFormData,
          headers: {
            'Accept': 'application/json'
          }
        }).then(function (response) {
          return response.ok ? response.json() : Promise.reject(new Error('Nie udalo sie przywrocic zadania.'));
        }).then(function (payload) {
          if (!payload || payload.ok !== true) {
            throw new Error(payload && payload.error ? payload.error : 'Nie udalo sie przywrocic zadania.');
          }

          window.location.href = '{$baseUrl|escape:'javascript'}?controller=taskboard&action=index&board_id=' + encodeURIComponent(restoreBoardId) + '&task_id=' + encodeURIComponent(restoreTaskId);
        }).catch(function (error) {
          window.alert(error && error.message ? error.message : 'Nie udalo sie przywrocic zadania.');
        });
        return;
      }

      var openTrigger = event.target.closest('[data-taskboard-open]');
      if (!openTrigger) {
        return;
      }

      var panelUrl = openTrigger.getAttribute('data-panel-url');
      if (!panelUrl) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      if (hasDraggedCard) {
        hasDraggedCard = false;
        return;
      }

      window.taskboardOpenTask(openTrigger);
    });

    {if $selectedBoard and $selectedTask}
    function showSelectedTaskPanel(attempt) {
      var selectedTaskOffcanvas = getTaskPanelInstance();
      if (selectedTaskOffcanvas) {
        selectedTaskOffcanvas.show();
        animateTaskDetails(taskPanelBody || document);
        return;
      }

      if (attempt < 20) {
        window.setTimeout(function () {
          showSelectedTaskPanel(attempt + 1);
        }, 100);
      }
    }

    showSelectedTaskPanel(0);
    {/if}

    {if $canWriteTaskboard and $openCreateBoardModal|default:false}
    function showCreateBoardModal(attempt) {
      var modalInstance = getCreateBoardModalInstance();
      if (modalInstance) {
        modalInstance.show();
        return;
      }

      if (attempt < 20) {
        window.setTimeout(function () {
          showCreateBoardModal(attempt + 1);
        }, 100);
      }
    }

    showCreateBoardModal(0);
    {/if}

    var audioContext = null;

    function playTaskboardTone(isDone) {
      var AudioContextCtor = window.AudioContext || window.webkitAudioContext;
      if (!AudioContextCtor) {
        return;
      }

      if (!audioContext) {
        audioContext = new AudioContextCtor();
      }

      if (audioContext.state === 'suspended') {
        audioContext.resume();
      }

      var oscillator = audioContext.createOscillator();
      var gain = audioContext.createGain();
      oscillator.type = 'sine';
      oscillator.frequency.value = isDone ? 620 : 320;
      gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.06, audioContext.currentTime + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.16);
      oscillator.connect(gain);
      gain.connect(audioContext.destination);
      oscillator.start();
      oscillator.stop(audioContext.currentTime + 0.18);
    }

    bindSubtaskCheckboxes(document);
    bindTaskDetailAutosave(document);
    bindSubtaskCreateForms(document);
    bindNoteCreateForms(document);
    bindRefreshPanelForms(document);
    bindStatusBuilder();
    bindPasteUpload();
    bindTaskboardEditLayers();
    bindOutsideCloseForEditLayers();
    updateTaskboardEditLayerState();
    animateSelectedTaskCard();
    animateTaskDetails(document);
    refreshColumnEmptyStates();
  })();
</script>
