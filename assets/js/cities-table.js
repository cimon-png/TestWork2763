document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('city-search');

    searchInput.addEventListener('input', function() {
        var searchTerm = searchInput.value;

        fetch(cities_table_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=cities_table_search&search_term=' + encodeURIComponent(searchTerm),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cities-table-container').innerHTML = data.data;
            }
        })
        .catch(error => console.error('Error:', error));
    });
});