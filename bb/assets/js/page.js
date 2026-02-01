

document.querySelectorAll('.page-filter-select').forEach((el) => {
    el.addEventListener('change', (e)=> {
        e.target.form.submit();
    });
});

