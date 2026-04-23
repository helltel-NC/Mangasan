document.addEventListener('DOMContentLoaded', () => {
    const story = document.getElementById('story_score');
    const art = document.getElementById('art_score');
    const universe = document.getElementById('universe_score');
    const message = document.getElementById('message_score');
    const preview = document.getElementById('reviewScorePreview');

    if (!story || !art || !universe || !message || !preview) {
        return;
    }

    const updateScore = () => {
        const values = [
            parseFloat(story.value || '0'),
            parseFloat(art.value || '0'),
            parseFloat(universe.value || '0'),
            parseFloat(message.value || '0')
        ];

        const total = values.reduce((sum, value) => sum + (Number.isFinite(value) ? value : 0), 0);
        const average = total / 4;

        preview.textContent = average.toFixed(2);
    };

    [story, art, universe, message].forEach((input) => {
        input.addEventListener('input', updateScore);
        input.addEventListener('change', updateScore);
    });

    updateScore();
});