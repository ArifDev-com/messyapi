// Custom JavaScript for ASP ERP

$(document).ready(function() {
    // Show loading spinner on form submissions
    // $('form').on('submit', function() {
    //     const loadingOverlay = $('#loading-overlay');
    //     if (loadingOverlay.length) {
    //         loadingOverlay.removeClass('d-none');
    //     }
    // });

    // Mobile sidebar toggle
    $('#sidebar-toggle').on('click', function(e) {
        e.stopPropagation();
        $('.sidebar').toggleClass('show');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function(e) {
        if ($(window).width() < 768) {
            if (!$(e.target).closest('.sidebar, #sidebar-toggle').length) {
                $('.sidebar').removeClass('show');
            }
        }
    });

    // Initialize Select2 on all select elements
    $('select').select2({
        theme: 'bootstrap',
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder') || 'Select an option';
        },
        allowClear: true
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Add active class to parent menu items when submenu is active
    $('.collapse').on('show.bs.collapse', function() {
        $(this).prev('.nav-link').addClass('active');
    });

    $('.collapse').on('hide.bs.collapse', function() {
        if (!$(this).find('.nav-link.active').length) {
            $(this).prev('.nav-link').removeClass('active');
        }
    });

    // Keep parent menu open when child is active
    $('.nav-link.active').parents('.collapse').addClass('show');
    $('.nav-link.active').parents('.collapse').prev('.nav-link').addClass('active');

    // Toggle chevron icon on collapse
    $('.collapse').on('show.bs.collapse', function() {
        $(this).prev('.nav-link').find('.fa-chevron-down').addClass('rotate');
    });

    $('.collapse').on('hide.bs.collapse', function() {
        $(this).prev('.nav-link').find('.fa-chevron-down').removeClass('rotate');
    });

    // Initialize already open menus with rotated chevron
    $('.collapse.show').prev('.nav-link').find('.fa-chevron-down').addClass('rotate');
});
