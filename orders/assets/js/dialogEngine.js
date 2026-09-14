/**
 * dialogEngine.js - Component for handles image modal dialogs
 */

class DialogEngine {
  constructor() {
    this.currentlyOpenDialog = null;
    this.backdrop = null;

    this._handleDocumentClick = this._handleDocumentClick.bind(this);
    this._handleKeydown = this._handleKeydown.bind(this);
    this._handleDialogTriggerClick = this._handleDialogTriggerClick.bind(this);

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.init());
    } else {
      this.init();
    }
  }

  init() {
    this._createBackdrop();
    this._setupEventListeners();
  }

  _createBackdrop() {
    this.backdrop = document.getElementById('dialogBackdrop');
    if (!this.backdrop) {
      this.backdrop = document.createElement('div');
      this.backdrop.className = 'dialog-backdrop';
      this.backdrop.id = 'dialogBackdrop';
      this.backdrop.addEventListener('click', () => this.closeAnyOpenDialogs());
      document.body.appendChild(this.backdrop);
    }
  }

  _setupEventListeners() {
    document.addEventListener('click', this._handleDocumentClick);
    document.addEventListener('keydown', this._handleKeydown);
    document.addEventListener('click', this._handleDialogTriggerClick);
  }

  _handleDialogTriggerClick(event) {
    const target = event.target;

    // Image triggers
    const trigger = target.closest('.linked-text-img');
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();

      const container = trigger.closest('.multi-link-container');
      if (container) {
        const dialog = container.querySelector('.image-dialog');
        if (dialog) {
          this.closeAnyOpenDialogs();
          this._showDialog(dialog);
        }
      }
      return;
    }

    // Info/Text triggers
    const infoTrigger = target.closest('.linked-text-info');
    if (infoTrigger) {
      event.preventDefault();
      event.stopPropagation();

      const container = infoTrigger.closest('.multi-link-container');
      if (container) {
        const dialog = container.querySelector('.info-dialog');
        if (dialog) {
          this.closeAnyOpenDialogs();
          this._showDialog(dialog);
        }
      }
      return;
    }

    if (target.closest('.btn-close-dialog')) {
      event.preventDefault();
      event.stopPropagation();
      this.closeAnyOpenDialogs();
    } else if (target.closest('.image-dialog') || target.closest('.info-dialog')) {
      event.stopPropagation();
    }
  }

  _handleDocumentClick(event) {
    if (this.currentlyOpenDialog && !this.currentlyOpenDialog.contains(event.target)) {
      this.closeAnyOpenDialogs();
    }
  }

  _handleKeydown(event) {
    if (!this.currentlyOpenDialog) return;
    if (event.key === 'Escape') {
      this.closeAnyOpenDialogs();
    }
  }

  _showDialog(dialog) {
    requestAnimationFrame(() => {
      dialog.classList.add('visible');
      if (this.backdrop) {
        this.backdrop.classList.add('active');
      }
      this.currentlyOpenDialog = dialog;
    });
  }

  closeAnyOpenDialogs() {
    if (!this.currentlyOpenDialog) return;

    requestAnimationFrame(() => {
      if (this.currentlyOpenDialog) {
        this.currentlyOpenDialog.classList.remove('visible');
      }
      if (this.backdrop) {
        this.backdrop.classList.remove('active');
      }
      this.currentlyOpenDialog = null;
    });
  }
}

window.dialogEngine = new DialogEngine();
