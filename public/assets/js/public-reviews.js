document.addEventListener('DOMContentLoaded', () => {
    const reviewLink = document.getElementById('detailReviewLink');
    const cards = document.querySelectorAll('.featured-card');

    if (!reviewLink || cards.length === 0) {
        return;
    }

    function applyReviewLink(card) {
        const reviewUrl = card.dataset.reviewUrl || '';
        const reviewLabel = card.dataset.reviewLabel || 'Ma review';

        reviewLink.textContent = reviewLabel;

        if (reviewUrl !== '') {
            reviewLink.href = reviewUrl;
            reviewLink.classList.remove('is-disabled');
            reviewLink.removeAttribute('aria-disabled');
        } else {
            reviewLink.href = '#';
            reviewLink.classList.add('is-disabled');
            reviewLink.setAttribute('aria-disabled', 'true');
        }
    }

    cards.forEach((card) => {
        card.addEventListener('click', () => {
            applyReviewLink(card);
        });

        card.addEventListener('mouseenter', () => {
            applyReviewLink(card);
        });

        card.addEventListener('focus', () => {
            applyReviewLink(card);
        });
    });

    if (cards.length === 1) {
        applyReviewLink(cards[0]);
    }

    reviewLink.addEventListener('click', (event) => {
        if (reviewLink.getAttribute('href') === '#') {
            event.preventDefault();
        }
    });
});