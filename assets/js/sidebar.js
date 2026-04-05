// assets/js/sidebar.js
$(document).ready(function() {
    // Sidebar toggle function
    function toggleSidebar() {
        $('#sidebar').toggleClass('active');
        $('#content').toggleClass('active');
        
        // Save state to localStorage
        var sidebarState = $('#sidebar').hasClass('active') ? 'collapsed' : 'expanded';
        localStorage.setItem('sidebarState', sidebarState);
        
        // Optional: Add animation effect
        $('#sidebar').css('transition', 'all 0.3s ease-in-out');
        $('#content').css('transition', 'all 0.3s ease-in-out');
    }
    
    // Bind click event
    $('#sidebarCollapse').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });
    
    // Load saved sidebar state
    var savedState = localStorage.getItem('sidebarState');
    if (savedState === 'collapsed') {
        $('#sidebar').addClass('active');
        $('#content').addClass('active');
    }
    
    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            if (savedState === 'collapsed') {
                $('#sidebar').addClass('active');
                $('#content').addClass('active');
            }
        } else {
            if (savedState === 'expanded') {
                $('#sidebar').removeClass('active');
                $('#content').removeClass('active');
            }
        }
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 3000);
    
    // Add animation to cards
    $('.card').addClass('fade-in');
    
    // Confirm delete
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
});