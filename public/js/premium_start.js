document.addEventListener('DOMContentLoaded', function () {
    // Tabs logic for Assortment block
    const tabs = document.querySelectorAll('.ps-tab');
    const tabContents = document.querySelectorAll('.ps-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs & contents
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));

            // Add active class to clicked tab and corresponding content
            tab.classList.add('active');
            const targetId = tab.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#ps-"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetEl = document.querySelector(this.getAttribute('href'));
            if (targetEl) {
                targetEl.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    // Modal logic to pass model_id to hidden input
    const orderModal = document.getElementById('ps-modal-order');
    if (orderModal) {
        orderModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            // Extract info from data-model attribute
            const modelId = button.getAttribute('data-model');
            const tariffName = button.getAttribute('data-tariff');

            // Update the modal's hidden input
            const inputModelId = orderModal.querySelector('input[name="model_id"]');
            if (inputModelId) {
                inputModelId.value = modelId;
            }

            // Update modal title with tariff name
            const titleTariffEl = orderModal.querySelector('#psModalTariffName');
            if (titleTariffEl) {
                titleTariffEl.textContent = tariffName ? tariffName : '';
            }
        });
    }
});
