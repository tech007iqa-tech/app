const Viewer = {
    currentRotation: 0,
    currentZoom: 1,

    rotateImage(deg) {
        this.currentRotation += deg;
        this.applyTransforms();
    },

    zoomImage(amount) {
        this.currentZoom += amount;
        if (this.currentZoom < 0.2) this.currentZoom = 0.2;
        if (this.currentZoom > 3) this.currentZoom = 3;
        this.applyTransforms();
    },

    applyTransforms() {
        const img = document.getElementById('active-preview');
        if (img) {
            img.style.transform = `rotate(${this.currentRotation}deg) scale(${this.currentZoom})`;
        }
    },

    resetViewer() {
        const stage = document.getElementById('viewer-stage');
        if (!stage) return;

        const existingImgs = stage.querySelectorAll('img');
        existingImgs.forEach(img => img.remove());
        const existingPs = stage.querySelectorAll('p');
        existingPs.forEach(p => p.remove());

        const p = document.createElement('p');
        p.style.color = 'var(--text-dim)';
        p.style.fontSize = '0.9rem';
        p.textContent = 'No image selected. Upload files.';
        stage.insertBefore(p, stage.firstChild);

        const controls = document.getElementById('viewer-controls');
        if (controls) {
            controls.style.display = 'none';
        }
        this.currentRotation = 0;
        this.currentZoom = 1;
    },

    preprocessImageForOCR(file) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                let width = img.width;
                let height = img.height;
                const maxDimension = 2000;
                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                try {
                    const imageData = ctx.getImageData(0, 0, width, height);
                    const data = imageData.data;

                    let min = 255;
                    let max = 0;
                    const len = data.length;
                    const brightnessValues = new Uint8Array(len / 4);

                    for (let i = 0; i < len; i += 4) {
                        const v = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                        brightnessValues[i / 4] = v;
                        if (v < min) min = v;
                        if (v > max) max = v;
                    }

                    const range = max - min || 1;
                    const threshold = min + range * 0.45;

                    for (let i = 0; i < len; i += 4) {
                        const v = brightnessValues[i / 4];
                        const newVal = v < threshold ? 0 : 255;
                        data[i] = newVal;
                        data[i + 1] = newVal;
                        data[i + 2] = newVal;
                    }

                    ctx.putImageData(imageData, 0, 0);
                } catch (e) {
                    console.error("Canvas pixel manipulation failed, using raw canvas:", e);
                }
                resolve(canvas);
            };
            img.src = URL.createObjectURL(file);
        });
    }
};
window.Viewer = Viewer;
