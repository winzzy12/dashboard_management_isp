        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Sidebar elements
    const sidebar = $('#sidebar');
    const content = $('#content');
    const toggleBtn = $('#toggleSidebarBtn');
    const overlay = $('#sidebarOverlay');
    
    // Check if we're on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Load saved sidebar state
    function loadSavedState() {
        if (isMobile()) {
            const savedVisible = localStorage.getItem('sidebarMobileVisible');
            if (savedVisible === 'true') {
                sidebar.addClass('visible');
                overlay.addClass('active');
            } else {
                sidebar.removeClass('visible');
                overlay.removeClass('active');
            }
            sidebar.removeClass('collapsed');
            content.removeClass('expanded');
        } else {
            const savedCollapsed = localStorage.getItem('sidebarDesktopCollapsed');
            if (savedCollapsed === 'true') {
                sidebar.addClass('collapsed');
                content.addClass('expanded');
            } else {
                sidebar.removeClass('collapsed');
                content.removeClass('expanded');
            }
            sidebar.removeClass('visible');
            overlay.removeClass('active');
        }
    }
    
    // Toggle sidebar function
    function toggleSidebar() {
        if (isMobile()) {
            if (sidebar.hasClass('visible')) {
                sidebar.removeClass('visible');
                overlay.removeClass('active');
                localStorage.setItem('sidebarMobileVisible', false);
            } else {
                sidebar.addClass('visible');
                overlay.addClass('active');
                localStorage.setItem('sidebarMobileVisible', true);
            }
        } else {
            if (sidebar.hasClass('collapsed')) {
                sidebar.removeClass('collapsed');
                content.removeClass('expanded');
                localStorage.setItem('sidebarDesktopCollapsed', false);
            } else {
                sidebar.addClass('collapsed');
                content.addClass('expanded');
                localStorage.setItem('sidebarDesktopCollapsed', true);
            }
        }
    }
    
    // Close sidebar on mobile when clicking overlay
    overlay.on('click', function() {
        if (isMobile()) {
            sidebar.removeClass('visible');
            overlay.removeClass('active');
            localStorage.setItem('sidebarMobileVisible', false);
        }
    });
    
    // Toggle sidebar when button is clicked
    toggleBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });
    
    // Handle window resize
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            loadSavedState();
        }, 250);
    });
    
    // Initial load
    loadSavedState();
    
    // Close sidebar on mobile when clicking a menu link (except submenu toggles)
    $('.sidebar-menu > li:not(.has-submenu) > a').on('click', function() {
        if (isMobile()) {
            sidebar.removeClass('visible');
            overlay.removeClass('active');
            localStorage.setItem('sidebarMobileVisible', false);
        }
    });
    
    // ========== SUBMENU FUNCTION ==========
    // Toggle submenu when clicking on parent
    $('.has-submenu > a').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $this = $(this);
        var $parent = $this.closest('.has-submenu');
        var $submenu = $parent.find('.submenu');
        var $chevron = $this.find('.chevron');
        
        // Close other submenus (optional - comment if you want multiple open)
        $('.has-submenu').not($parent).each(function() {
            $(this).find('.submenu').removeClass('show');
            $(this).removeClass('active');
            $(this).find('.chevron').css('transform', 'rotate(0deg)');
        });
        
        // Toggle current submenu
        $submenu.toggleClass('show');
        $parent.toggleClass('active');
        
        // Rotate chevron
        if ($submenu.hasClass('show')) {
            $chevron.css('transform', 'rotate(90deg)');
        } else {
            $chevron.css('transform', 'rotate(0deg)');
        }
        
        return false;
    });
    
    // For submenu links, navigate normally
    $('.submenu a').on('click', function(e) {
        e.stopPropagation();
        // Allow navigation to proceed
        window.location.href = $(this).attr('href');
    });
    
    // Ensure submenu stays open if active page is inside
    $('.has-submenu').each(function() {
        var $this = $(this);
        var $submenu = $this.find('.submenu');
        if ($submenu.find('.active').length > 0) {
            $submenu.addClass('show');
            $this.addClass('active');
            $this.find('.chevron').css('transform', 'rotate(90deg)');
        }
    });
    
    // Auto-hide alerts after 3 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 3000);
    
    // Add fade-in animation to cards
    $('.card').addClass('fade-in');
    
    // Confirm delete
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    
    // Tooltip initialization
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Popover initialization
    $('[data-bs-toggle="popover"]').popover();
});
</script>
</body>
</html>