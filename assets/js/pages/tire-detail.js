/**
 * Tire detail page — gallery thumbnail switching
 */
document.addEventListener('DOMContentLoaded', () => {
  const thumbs = document.querySelectorAll('.tire-detail__thumb');
  const mainImage = document.querySelector('.tire-detail__main-image');

  if (!thumbs.length || !mainImage) return;

  thumbs.forEach((thumb) => {
    thumb.addEventListener('click', () => {
      // Update active state
      thumbs.forEach((t) => t.classList.remove('active'));
      thumb.classList.add('active');

      // Swap main image with clicked thumbnail's picture
      const picture = thumb.querySelector('picture');
      if (!picture) return;

      const newPicture = picture.cloneNode(true);
      const img = newPicture.querySelector('img');
      if (img) {
        img.classList.add('tire-detail__img');
        img.loading = 'eager';
      }

      mainImage.innerHTML = '';
      mainImage.appendChild(newPicture);
    });
  });
});
