const initReadingGoalSlider = () => {
    const slider = document.querySelector('[data-reading-goal-slider]');
    const output = document.querySelector('[data-reading-goal-output]');

    if (!slider || !output) {
        return;
    }

    slider.addEventListener('input', () => {
        output.textContent = slider.value;
    });
};

export { initReadingGoalSlider };