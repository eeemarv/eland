import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

  static targets = [
    'input',
    'dropzone',
    'previewList'
  ];

  static values = {
    csrf: String,
    uploadPath: String,
  };

  browse() {
    this.inputTarget.click()
  }

  select(event) {
    this.handleFiles(event.target.files)
  }

  dragOver(event) {
    event.preventDefault()
    this.dropzoneTarget.classList.add('drag-over')
  }

  dragLeave(event) {
    event.preventDefault()
    this.dropzoneTarget.classList.remove('drag-over')
  }

  drop(event) {
    event.preventDefault()
    this.dropzoneTarget.classList.remove('drag-over');
    this.handleFiles(event.dataTransfer.files);
  }

  async handleFiles(fileList) {

    const files = Array.from(fileList);

    for (const file of files) {

      if (!file.type.startsWith('image/')) {
        continue;
      }

      const preview = this.createPreview();

      try {

        const processedBlob = await this.processImage(file);

        await this.uploadImage(
          processedBlob,
          file.name,
          preview
        );

      } catch (error) {
        console.error(error);
        preview.classList.add('upload-error');
      }
    }
  }

  createPreview() {
    const element = document.createElement('div')

    element.className = 'upload-preview'

    element.innerHTML = `
      <div class="upload-thumb"></div>
      <div class="upload-progress">
        <div class="upload-progress-bar"></div>
      </div>
    `

    this.previewListTarget.appendChild(element)

    return element;
  }

  async processImage(file) {

    const bitmap = await createImageBitmap(file, {
      imageOrientation: 'from-image',
    });

    let width = bitmap.width
    let height = bitmap.height

    const maxSize = 1600

    if (width > maxSize || height > maxSize) {

      if (width > height) {
        height *= maxSize / width;
        width = maxSize;
      } else {
        width *= maxSize / height;
        height = maxSize;
      }
    }

    const canvas = document.createElement('canvas')

    canvas.width = Math.round(width)
    canvas.height = Math.round(height)

    const ctx = canvas.getContext('2d')

    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height)

    return await new Promise(resolve => {
      canvas.toBlob(
        blob => resolve(blob),
        'image/webp',
        0.82
      );
    })
  }

  async uploadImage(blob, originalName, preview) {

    const formData = new FormData();

    formData.append(
      'file',
      blob,
      originalName + '.webp'
    );

    formData.append(
      '_token',
      this.csrfValue
    );

    return new Promise((resolve, reject) => {

      const xhr = new XMLHttpRequest();

      xhr.open('POST', this.uploadPathValue);

      xhr.upload.addEventListener('progress', event => {

        if (!event.lengthComputable) {
          return;
        }

        const progress =
          (event.loaded / event.total) * 100;

        preview
          .querySelector('.upload-progress-bar')
          .style.width = progress + '%';
      });

      xhr.onload = () => {

        if (xhr.status >= 200 && xhr.status < 300) {

          const response = JSON.parse(xhr.responseText);

          preview
            .querySelector('.upload-thumb')
            .innerHTML = `
                <img src="${response.thumbnailUrl}">
            `;

          resolve(response);

        } else {
          reject(xhr.responseText);
        }
      }

      xhr.onerror = reject;

      xhr.send(formData);
    })
  }
}