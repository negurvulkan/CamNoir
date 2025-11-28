const imageEl = document.getElementById('slideshow-image');
const metaEl = document.getElementById('slideshow-meta');
let photos = window.SLIDESHOW?.photos || [];
let index = 0;
let lastTimestamp = photos[0]?.created_at;

function showCurrent() {
    if (!photos.length) {
        metaEl.textContent = 'Warten auf freigegebene Fotos ...';
        return;
    }
    const photo = photos[index % photos.length];
    if (photo) {
        const publicPath = photo.file_path ? photo.file_path.replace(/^.*public\//, '') : '';
        imageEl.src = publicPath ? `${location.origin}/${publicPath}` : imageEl.src;
        metaEl.textContent = `${photo.delete_code || ''} • ${photo.created_at || ''}`;
    }
    index++;
}

async function fetchLatest() {
    try {
        const url = new URL(window.SLIDESHOW.liveUrl);
        if (lastTimestamp) {
            url.searchParams.set('since', lastTimestamp);
        }
        const res = await fetch(url.toString());
        const json = await res.json();
        if (json.photos && json.photos.length) {
            photos = json.photos.concat(photos).sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            lastTimestamp = photos[0].created_at;
        }
    } catch (e) {
        console.warn('Live-Update fehlgeschlagen', e);
    }
}

setInterval(showCurrent, 5000);
setInterval(fetchLatest, 6000);
showCurrent();
