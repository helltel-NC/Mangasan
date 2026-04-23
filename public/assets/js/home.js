
document.addEventListener('DOMContentLoaded', function () {
const track = document.getElementById('featuredTrack');
const prev = document.querySelector('.carousel-btn.prev');
const next = document.querySelector('.carousel-btn.next');

const cards = document.querySelectorAll('.featured-card');
const overlay = document.getElementById('detailOverlay');
const panel = document.getElementById('detailPanel');
const closeBtn = document.getElementById('detailClose');
const detailTitle = document.getElementById('detailTitle');
const detailDesc = document.getElementById('detailDesc');
const detailExtra = document.getElementById('detailExtra');
const detailCover = document.getElementById('detailCover');

if (!track) {
    return;
}

let isDown = false;
let startX = 0;
let scrollLeft = 0;
let moved = false;

const getStep = () => {
    const card = track.querySelector('.featured-card');
    if (!card) {
        return 320;
    }
    const style = window.getComputedStyle(track);
    const gap = parseInt(style.columnGap || style.gap || 22, 10);
    return card.offsetWidth + gap;
};

track.addEventListener('mousedown', function (e) {
    isDown = true;
    moved = false;
    track.classList.add('dragging');
    startX = e.pageX - track.offsetLeft;
    scrollLeft = track.scrollLeft;
});

window.addEventListener('mouseup', function () {
    isDown = false;
    track.classList.remove('dragging');
});

window.addEventListener('mousemove', function (e) {
    if (!isDown) {
        return;
    }

    e.preventDefault();
    const x = e.pageX - track.offsetLeft;
    const walk = (x - startX) * 1.2;

    if (Math.abs(walk) > 5) {
        moved = true;
    }

    track.scrollLeft = scrollLeft - walk;
});

track.addEventListener('dragstart', function (e) {
    e.preventDefault();
});

track.addEventListener('wheel', function (e) {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        e.preventDefault();
        track.scrollLeft += e.deltaY;
    }
}, { passive: false });

if (prev) {
    prev.addEventListener('click', function () {
        track.scrollBy({
            left: -getStep(),
            behavior: 'smooth'
        });
    });
}

if (next) {
    next.addEventListener('click', function () {
        track.scrollBy({
            left: getStep(),
            behavior: 'smooth'
        });
    });
}

function openDetail(card) {
    detailTitle.textContent = card.dataset.title || '';
    detailDesc.textContent = card.dataset.desc || '';
    detailExtra.textContent = card.dataset.extra || '';
    detailCover.textContent = card.dataset.cover || 'Couverture';

    overlay.classList.add('is-visible');

    requestAnimationFrame(function () {
        panel.classList.add('is-animated');
    });
}

function closeDetail() {
    panel.classList.remove('is-animated');

    setTimeout(function () {
        overlay.classList.remove('is-visible');
    }, 420);
}

cards.forEach(function (card) {
    card.addEventListener('click', function () {
        if (moved) {
            return;
        }
        openDetail(card);
    });
});

if (closeBtn) {
    closeBtn.addEventListener('click', closeDetail);
}

if (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeDetail();
        }
    });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay && overlay.classList.contains('is-visible')) {
        closeDetail();
    }
});
});

