// Main JavaScript for RT/RW Net Management

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Confirm delete
    $('.delete-confirm').on('click', function(e) {
        if(!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    
    // Date range picker
    if($('#date-range').length) {
        // You can add date range picker plugin here
    }
    
    // Filter form submit
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var url = window.location.pathname + '?' + formData;
        window.location.href = url;
    });
    
    // Export buttons
    $('.export-pdf').on('click', function() {
        window.print();
    });
    
    $('.export-excel').on('click', function() {
        var table = $('.datatable').DataTable();
        // Implement Excel export functionality
        alert('Fitur export Excel akan segera hadir');
    });
    
    // Search functionality
    $('#search-input').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.searchable-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // Status badge color change
    $('.status-badge').each(function() {
        var status = $(this).text().toLowerCase();
        if(status === 'aktif' || status === 'lunas') {
            $(this).addClass('badge-success');
        } else if(status === 'nonaktif' || status === 'belum_lunas') {
            $(this).addClass('badge-danger');
        } else {
            $(this).addClass('badge-warning');
        }
    });
    
    // Auto calculate total in forms
    $('.auto-calc').on('keyup', function() {
        var total = 0;
        $('.auto-calc').each(function() {
            var val = parseFloat($(this).val());
            if(!isNaN(val)) {
                total += val;
            }
        });
        $('#total-amount').val(formatRupiah(total));
    });
    
    // Format currency function
    function formatRupiah(angka) {
        var rupiah = '';
        var angkarev = angka.toString().split('').reverse().join('');
        for(var i = 0; i < angkarev.length; i++) 
            if(i % 3 == 0) rupiah += angkarev.substr(i,3) + '.';
        return 'Rp ' + rupiah.split('',rupiah.length-1).reverse().join('');
    }
    
    // Live preview for forms
    $('#nama-pelanggan').on('keyup', function() {
        $('#preview-nama').text($(this).val());
    });
    
    // Chart initialization
    if($('#incomeChart').length) {
        initIncomeChart();
    }
    
    if($('#expenseChart').length) {
        initExpenseChart();
    }
    
    // Function to initialize income chart
    function initIncomeChart() {
        var ctx = document.getElementById('incomeChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Pemasukan',
                    data: [12000000, 15000000, 18000000, 22000000, 25000000, 28000000],
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(40, 167, 69, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Function to initialize expense chart
    function initExpenseChart() {
        var ctx = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                    label: 'Pengeluaran',
                    data: [5000000, 6000000, 5500000, 7000000, 6500000, 8000000],
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Sidebar toggle animation
    $('#sidebarCollapse').on('click', function() {
        $('#sidebar').toggleClass('active');
        $('#content').toggleClass('active');
        
        // Save state to localStorage
        var sidebarState = $('#sidebar').hasClass('active') ? 'collapsed' : 'expanded';
        localStorage.setItem('sidebarState', sidebarState);
    });
    
    // Load sidebar state
    var savedState = localStorage.getItem('sidebarState');
    if(savedState === 'collapsed') {
        $('#sidebar').addClass('active');
        $('#content').addClass('active');
    }
    
    // Form validation
    $('.needs-validation').on('submit', function(e) {
        if(this.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Date validation
    $('#tanggal-awal, #tanggal-akhir').on('change', function() {
        var start = $('#tanggal-awal').val();
        var end = $('#tanggal-akhir').val();
        
        if(start && end && start > end) {
            alert('Tanggal awal tidak boleh lebih besar dari tanggal akhir!');
            $(this).val('');
        }
    });
    
    // Number input validation
    $('input[type="number"]').on('keypress', function(e) {
        var charCode = (e.which) ? e.which : e.keyCode;
        if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    
    // Loading spinner
    function showLoading() {
        $('.spinner-overlay').fadeIn();
    }
    
    function hideLoading() {
        $('.spinner-overlay').fadeOut();
    }
    
    // AJAX form submission
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action');
        var data = form.serialize();
        
        showLoading();
        
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(response) {
                hideLoading();
                if(response.success) {
                    showNotification('success', response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', response.message);
                }
            },
            error: function() {
                hideLoading();
                showNotification('error', 'Terjadi kesalahan pada server');
            }
        });
    });
    
    // Notification system
    function showNotification(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var notification = $('<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        
        $('.notification-area').prepend(notification);
        
        setTimeout(function() {
            notification.fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Print report
    $('.print-report').on('click', function() {
        var originalTitle = document.title;
        document.title = 'Laporan RT/RW Net';
        window.print();
        document.title = originalTitle;
    });
    
    // Refresh data
    $('.refresh-data').on('click', function() {
        showLoading();
        setTimeout(function() {
            location.reload();
        }, 500);
    });
    
    // Auto-complete for search
    $('#auto-complete').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: 'search.php',
                dataType: 'json',
                data: {
                    term: request.term
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2
    });
});