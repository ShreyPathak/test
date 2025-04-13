jQuery(document).ready(function($) {
    // Use data from PHP
    var csv_data = bank_ifcs_data.csv_data;
    var csv_headers = bank_ifcs_data.csv_headers;
    var column_mapping = bank_ifcs_data.column_mapping;

    if (!csv_data || !csv_headers || !column_mapping) {
        alert('No CSV data available. Please upload and map a CSV file in the admin settings.');
        return;
    }

    // Map indices from column_mapping
    var bankIndex = column_mapping.bank_name || 0;
    var branchIndex = column_mapping.branch_name || 0;
    var cityIndex = column_mapping.city_district || 0;
    var stateIndex = column_mapping.state || 0;
    var ifscIndex = column_mapping.ifsc_code || 0;

    // Organize data by Bank -> State -> City -> Branch
    var bankData = {};

    $.each(csv_data, function(index, row) {
        var bank = row[bankIndex].toLowerCase().replace(/\s+/g, '-');
        var state = row[stateIndex].toLowerCase().replace(/\s+/g, '-');
        var city = row[cityIndex].toLowerCase().replace(/\s+/g, '-');
        var branch = row[branchIndex].toLowerCase().replace(/\s+/g, '-');
        var ifsc = row[ifscIndex];

        if (!bankData[bank]) bankData[bank] = {};
        if (!bankData[bank][state]) bankData[bank][state] = {};
        if (!bankData[bank][state][city]) bankData[bank][state][city] = {};
        if (!bankData[bank][state][city][branch]) bankData[bank][state][city][branch] = ifsc;
    });

    // Populate Bank Name dropdown
    var banks = Object.keys(bankData).sort();
    var bankDropdown = $('#bank_name');
    bankDropdown.html('<option value="">Select Bank</option>');
    $.each(banks, function(index, bank) {
        bankDropdown.append('<option value="' + bank + '">' + bank.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</option>');
    });

    // Initial setup
    $('#state, #city, #branch').prop('disabled', true);

    // Bank Name change event
    $('#bank_name').on('change', function() {
        var bank = $(this).val();
        if (bank) {
            $('#state').prop('disabled', false).html('<option value="">Select State</option>');
            var states = Object.keys(bankData[bank] || {}).sort();
            $.each(states, function(index, state) {
                $('#state').append('<option value="' + state + '">' + state.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</option>');
            });
        } else {
            resetDropdowns();
        }
        updateTable();
    });

    // State change event
    $('#state').on('change', function() {
        var bank = $('#bank_name').val();
        var state = $(this).val();
        if (state) {
            $('#city').prop('disabled', false).html('<option value="">Select City/District</option>');
            var cities = Object.keys(bankData[bank][state] || {}).sort();
            $.each(cities, function(index, city) {
                $('#city').append('<option value="' + city + '">' + city.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</option>');
            });
        } else {
            $('#city').prop('disabled', true).html('<option value="">Select City/District</option>');
            $('#branch').prop('disabled', true).html('<option value="">Select Branch</option>');
        }
        updateTable();
    });

    // City/District change event
    $('#city').on('change', function() {
        var bank = $('#bank_name').val();
        var state = $('#state').val();
        var city = $(this).val();
        if (city) {
            $('#branch').prop('disabled', false).html('<option value="">Select Branch</option>');
            var branches = Object.keys(bankData[bank][state][city] || {}).sort();
            $.each(branches, function(index, branch) {
                var ifsc = bankData[bank][state][city][branch];
                $('#branch').append('<option value="' + branch + '" data-ifsc="' + ifsc + '">' + branch.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</option>');
            });
        } else {
            $('#branch').prop('disabled', true).html('<option value="">Select Branch</option>');
        }
        updateTable();
    });

    // Branch change event (for updating table)
    $('#branch').on('change', function() {
        updateTable();
    });

    // Function to reset all dropdowns except Bank Name
    function resetDropdowns() {
        $('#state').prop('disabled', true).html('<option value="">Select State</option>');
        $('#city').prop('disabled', true).html('<option value="">Select City/District</option>');
        $('#branch').prop('disabled', true).html('<option value="">Select Branch</option>');
        updateTable();
    }

    // Function to update the table with selected values
    function updateTable() {
        var bankName = $('#bank_name option:selected').text() || '';
        var stateName = $('#state option:selected').text() || '';
        var cityName = $('#city option:selected').text() || '';
        var branchName = $('#branch option:selected').text() || '';
        var ifscCode = $('#branch option:selected').data('ifsc') || '';

        var tableHtml = `
            <table class="bank-details-table">
                <thead>
                    <tr>
                        <th>Detail</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Bank Name:</td><td>${bankName}</td></tr>
                    <tr><td>State Name:</td><td>${stateName}</td></tr>
                    <tr><td>City/District Name:</td><td>${cityName}</td></tr>
                    <tr><td>Bank Branch Name:</td><td>${branchName}</td></tr>
                    <tr><td>Bank IFSC Code:</td><td>${ifscCode}</td></tr>
                </tbody>
            </table>
        `;

        $('.bank-details-table').remove(); // Remove existing table
        $('.bank-ifcs-container').after(tableHtml); // Add new table after dropdowns
    }
});
