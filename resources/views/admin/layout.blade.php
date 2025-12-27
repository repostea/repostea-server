<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin Panel') - {{ config('site.name') }}</title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-title" content="{{ config('site.name') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-common.css') }}">
    <style>
        /* Enhanced active menu item styling */
        .menu-item {
            position: relative;
            transition: all 0.2s ease-in-out;
        }
        .menu-item.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%);
            border-left: 4px solid #3b82f6;
            padding-left: calc(1.5rem - 4px);
        }
        .menu-item.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #3b82f6;
            border-radius: 4px 0 0 4px;
        }
        .menu-item:hover {
            transform: translateX(2px);
        }

        /* Custom scrollbar */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.8);
        }

        /* Section headers - removed sticky to allow proper scrolling */
        .section-header {
            background: #1f2937;
        }

        /* Scroll shadows */
        .scroll-container {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .scroll-shadow-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to bottom, rgba(31, 41, 55, 0.8), transparent);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 5;
        }
        .scroll-shadow-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to top, rgba(31, 41, 55, 0.8), transparent);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 5;
        }
        .scroll-shadow-top.visible,
        .scroll-shadow-bottom.visible {
            opacity: 1;
        }

        /* Smooth scroll */
        .custom-scrollbar {
            scroll-behavior: smooth;
        }

        /* Sidebar collapse - Desktop */
        @media (min-width: 768px) {
            .sidebar {
                transition: width 0.3s ease;
            }

            .main-content {
                transition: margin-left 0.3s ease;
            }

            body.sidebar-collapsed .sidebar {
                width: 4rem !important;
            }

            /* Hide sidebar header text, keep logo */
            body.sidebar-collapsed .sidebar h1 span,
            body.sidebar-collapsed .sidebar > div:first-child p {
                display: none;
            }

            /* Center logo when collapsed */
            body.sidebar-collapsed .sidebar h1 {
                justify-content: center;
                padding: 0;
            }

            body.sidebar-collapsed .sidebar h1 img {
                margin: 0 auto;
            }

            /* Hide section titles */
            body.sidebar-collapsed .sidebar .section-header {
                display: none;
            }

            body.sidebar-collapsed .sidebar .border-t {
                display: none;
            }

            /* Center menu icons */
            body.sidebar-collapsed .sidebar .menu-item {
                justify-content: center;
                padding: 0.75rem 0 !important;
                font-size: 0;
            }

            body.sidebar-collapsed .sidebar .menu-item i {
                margin-right: 0 !important;
                font-size: 1.25rem;
            }

            /* Hide notification badges */
            body.sidebar-collapsed .sidebar .menu-item .bg-red-500,
            body.sidebar-collapsed .sidebar .menu-item .bg-orange-500 {
                display: none;
            }

            /* Adjust active border */
            body.sidebar-collapsed .sidebar .menu-item.active {
                padding-left: 0 !important;
                border-left: none;
            }

            /* Logout button when collapsed - icon only */
            body.sidebar-collapsed .sidebar form button {
                padding: 0.75rem 0 !important;
                font-size: 0 !important;
            }

            body.sidebar-collapsed .sidebar form button i {
                font-size: 1.25rem !important;
                margin-right: 0 !important;
            }

            /* Hide collapse button on mobile, show on desktop */
            #sidebarCollapseContainer {
                display: none !important;
            }

            #sidebarCollapseContainer .sidebar-collapse-btn {
                display: none !important;
            }

            @media (min-width: 768px) {
                #sidebarCollapseContainer {
                    display: block !important;
                }

                #sidebarCollapseContainer .sidebar-collapse-btn {
                    display: flex !important;
                }
            }

            /* Collapse button styling when sidebar is collapsed (desktop only) */
            @media (min-width: 768px) {
                body.sidebar-collapsed .sidebar-collapse-container {
                    background: #1f2937 !important;
                    display: block !important;
                    z-index: 9999 !important;
                }

                body.sidebar-collapsed .sidebar-collapse-btn {
                    justify-content: center !important;
                    background: #1f2937 !important;
                    display: flex !important;
                    padding: 12px !important;
                }

                body.sidebar-collapsed .sidebar-collapse-text {
                    display: none !important;
                }

                body.sidebar-collapsed .sidebar-collapse-icon {
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                    color: #d1d5db !important;
                    font-size: 16px !important;
                }

                /* Logout button more visible when collapsed */
                body.sidebar-collapsed .sidebar form button {
                    padding: 0.75rem 0 !important;
                    font-size: 0 !important;
                    background: transparent !important;
                    box-shadow: none !important;
                }

                body.sidebar-collapsed .sidebar form button i {
                    font-size: 1.25rem !important;
                    margin-right: 0 !important;
                    color: #ef4444 !important;
                }
            }
        }

        /* Toggle button styling */
        .sidebar-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle-btn i {
            transition: transform 0.3s ease;
        }

        /* Collapse button icon transition */
        .sidebar-collapse-icon {
            transition: transform 0.3s ease;
        }

        /* Submenu styles */
        .submenu {
            background-color: #1f2937;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }

        .submenu.hidden {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }

        .submenu:not(.hidden) {
            max-height: 500px;
            opacity: 1;
        }

        .submenu-item {
            border-left: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .submenu-item:hover {
            border-left-color: #3b82f6;
        }

        .submenu-item.active {
            border-left-color: #3b82f6;
            background-color: #374151;
        }

        /* Hide submenu when sidebar is collapsed */
        @media (min-width: 768px) {
            body.sidebar-collapsed .spam-abuse-menu .submenu {
                display: none !important;
            }

            body.sidebar-collapsed .spam-abuse-menu button {
                justify-content: center;
                padding: 0.75rem 0 !important;
            }

            body.sidebar-collapsed .spam-abuse-menu button span {
                font-size: 0;
            }

            body.sidebar-collapsed .spam-abuse-menu button i:first-child {
                margin-right: 0 !important;
                font-size: 1.25rem;
            }

            body.sidebar-collapsed .spam-abuse-menu button #spamAbuseChevron {
                display: none;
            }
        }

        /* Mobile-optimized admin content */
        @media (max-width: 768px) {
            /* Header optimizations - more compact and sticky */
            header {
                position: sticky;
                top: 0;
                z-index: 30;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                background: white;
            }

            header h2 {
                font-size: 0.875rem !important; /* 14px - smaller */
                line-height: 1.2 !important;
                font-weight: 600 !important;
            }

            /* Smaller avatar on mobile */
            header .rounded-full {
                width: 2rem !important; /* 32px */
                height: 2rem !important; /* 32px */
            }

            /* User info in header - hide on mobile, show only avatar */
            header .text-right {
                display: none !important;
            }

            /* User dropdown menu - ensure it's visible and positioned correctly */
            #userMenu {
                position: fixed !important;
                right: 0.5rem !important;
                top: 3rem !important; /* Adjusted for compact header */
                width: calc(100vw - 1rem) !important;
                max-width: 16rem !important;
                box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            }

            /* Button groups - stack vertically on mobile (only in main content, not sidebar/header) */
            main .flex.items-center.justify-between {
                flex-direction: column !important;
                gap: 0.75rem !important;
                align-items: stretch !important;
            }

            /* Nested button groups (only in main content) */
            main .flex.items-center.space-x-4,
            main .flex.items-center.space-x-3,
            main .flex.items-center.space-x-2 {
                flex-direction: column !important;
                gap: 0.5rem !important;
                width: 100% !important;
            }

            /* Buttons in stacked groups should be full width (only in main content) */
            main .flex.items-center.justify-between button,
            main .flex.items-center.justify-between a,
            main .flex.items-center.space-x-4 button,
            main .flex.items-center.space-x-4 a,
            main .flex.items-center.space-x-3 button,
            main .flex.items-center.space-x-3 a {
                width: 100% !important;
                justify-content: center !important;
            }

            /* Keep header flex layout normal on mobile */
            header .flex.items-center {
                flex-direction: row !important;
            }

            header .flex.items-center.space-x-2,
            header .flex.items-center.space-x-3,
            header .flex.items-center.space-x-4 {
                flex-direction: row !important;
                width: auto !important;
            }

            /* Responsive tables with horizontal scroll */
            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                width: 100%;
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.5rem;
                position: relative;
            }

            /* Add scroll indicator shadow */
            .table-responsive::after {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 30px;
                background: linear-gradient(to left, rgba(255,255,255,0.9), transparent);
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s;
            }

            .table-responsive.can-scroll::after {
                opacity: 1;
            }

            /* Force all tables to be scrollable */
            main table {
                display: table !important;
                width: 100% !important;
                min-width: 600px !important; /* Minimum width to trigger scroll */
                font-size: 0.813rem; /* 13px */
                border-collapse: separate;
                border-spacing: 0;
            }

            table th {
                padding: 0.625rem 0.5rem !important;
                font-size: 0.75rem; /* 12px */
                white-space: nowrap;
                background: #f9fafb;
                position: sticky;
                top: 0;
                z-index: 1;
            }

            table td {
                padding: 0.625rem 0.5rem !important;
                font-size: 0.813rem; /* 13px */
                white-space: nowrap;
                background: white;
            }

            /* First column sticky for better UX */
            table th:first-child,
            table td:first-child {
                position: sticky;
                left: 0;
                z-index: 2;
                background: white;
                box-shadow: 2px 0 4px rgba(0,0,0,0.05);
            }

            table th:first-child {
                background: #f9fafb;
                z-index: 3;
            }

            /* Ensure parent containers don't block scroll */
            main .bg-white, main .rounded-lg {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            /* Cards containing tables */
            .bg-white:has(table) {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                border-radius: 0.5rem;
            }

            /* Scrollbar styling for tables */
            main table::-webkit-scrollbar,
            .table-responsive::-webkit-scrollbar,
            .bg-white:has(table)::-webkit-scrollbar {
                height: 8px;
            }

            main table::-webkit-scrollbar-track,
            .table-responsive::-webkit-scrollbar-track,
            .bg-white:has(table)::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            main table::-webkit-scrollbar-thumb,
            .table-responsive::-webkit-scrollbar-thumb,
            .bg-white:has(table)::-webkit-scrollbar-thumb {
                background: #3b82f6;
                border-radius: 4px;
            }

            main table::-webkit-scrollbar-thumb:hover,
            .table-responsive::-webkit-scrollbar-thumb:hover,
            .bg-white:has(table)::-webkit-scrollbar-thumb:hover {
                background: #2563eb;
            }

            /* Smaller text sizes */
            h1 { font-size: 1.5rem !important; }
            h2 { font-size: 1.125rem !important; }
            h3 { font-size: 1rem !important; }
            h4 { font-size: 0.938rem !important; }

            /* Cards and containers */
            .bg-white, .card {
                margin-bottom: 1rem;
                border-radius: 0.5rem;
            }

            /* Buttons more compact */
            button, .btn {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.875rem !important;
            }

            /* Form inputs */
            input, select, textarea {
                font-size: 0.875rem !important;
            }

            /* Stats cards - stack vertically */
            .grid {
                gap: 0.75rem !important;
            }

            /* Badges and labels */
            .badge, .label, span[class*="bg-"] {
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
            }

            /* Action buttons in tables */
            table .btn, table button, table a[class*="btn"] {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.75rem !important;
            }

            /* Modal dialogs */
            .modal-content, [role="dialog"] {
                margin: 1rem !important;
                max-width: calc(100vw - 2rem) !important;
            }

            /* Reduce padding on content sections */
            .space-y-6 > * {
                margin-top: 1rem !important;
                margin-bottom: 1rem !important;
            }

            /* Main content padding - with small right padding on mobile for breathing room */
            main {
                padding: 0.875rem 0.5rem 0.875rem 0.875rem !important;
            }

            /* Add swipe hint for tables */
            .table-responsive.can-scroll::before {
                content: '← Swipe →';
                position: absolute;
                bottom: 0.5rem;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(59, 130, 246, 0.9);
                color: white;
                padding: 0.25rem 0.75rem;
                border-radius: 1rem;
                font-size: 0.75rem;
                font-weight: 600;
                z-index: 10;
                animation: fadeOut 3s forwards;
                pointer-events: none;
            }

            @keyframes fadeOut {
                0%, 50% { opacity: 1; }
                100% { opacity: 0; }
            }

            /* Pagination */
            .pagination {
                font-size: 0.813rem !important;
            }

            .pagination a, .pagination span {
                padding: 0.375rem 0.625rem !important;
            }

            /* Search and filter forms */
            .filters, .search-form {
                gap: 0.5rem !important;
            }

            /* Icon sizes */
            i.fa, i.fas, i.far {
                font-size: 0.875rem;
            }

            /* Overflow text with ellipsis */
            .text-overflow {
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Form labels and sections */
            label {
                font-size: 0.875rem !important;
            }

            /* Reduce spacing in forms */
            form .space-y-4 > * {
                margin-top: 0.75rem !important;
                margin-bottom: 0.75rem !important;
            }

            /* Alerts and notifications */
            .alert, [class*="bg-green-"], [class*="bg-red-"], [class*="bg-yellow-"] {
                font-size: 0.875rem !important;
                padding: 0.75rem !important;
            }

            /* Reduce excessive padding on content wrappers */
            .p-8, [class*="p-8"] {
                padding: 1rem !important;
            }

            .px-8 {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            .py-8 {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }

            /* Responsive grids - reduce gap further */
            .grid-cols-1.md\\:grid-cols-3 {
                gap: 0.75rem !important;
            }

            /* Info banners */
            .bg-blue-50, .bg-yellow-50, .bg-green-50 {
                padding: 0.75rem !important;
                font-size: 0.875rem !important;
            }

            .bg-blue-50 h3, .bg-yellow-50 h3, .bg-green-50 h3 {
                font-size: 0.938rem !important;
            }

            /* Stats cards - reduce large numbers */
            .text-3xl {
                font-size: 1.5rem !important; /* 24px instead of 30px */
                line-height: 1.2 !important;
            }

            .text-2xl {
                font-size: 1.25rem !important; /* 20px instead of 24px */
                line-height: 1.2 !important;
            }

            .text-xl {
                font-size: 1.125rem !important; /* 18px instead of 20px */
            }

            /* Stat card padding */
            .bg-white.rounded-lg.shadow.p-6,
            .p-6 {
                padding: 0.875rem !important;
            }

            .p-4 {
                padding: 0.75rem !important;
            }

            /* Icon containers in cards */
            .rounded-full.p-3 {
                padding: 0.5rem !important;
                width: 2.5rem !important;
                height: 2.5rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .rounded-full i {
                font-size: 1rem !important;
            }

            /* Dashboard grid spacing */
            .grid.gap-6 {
                gap: 0.875rem !important;
            }

            .grid.gap-4 {
                gap: 0.75rem !important;
            }

            /* Make stat cards more compact */
            main .bg-white.rounded-lg.shadow {
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            }
        }

        /* Mobile menu */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none; /* Don't block clicks when hidden */
        }
        .mobile-menu-overlay.active {
            opacity: 1;
            pointer-events: auto; /* Allow clicks when visible */
        }

        @media (max-width: 768px) {
            .mobile-menu-overlay {
                display: block;
            }

            /* Hide sidebar completely on mobile - it becomes an overlay */
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 85vw; /* 85% of viewport width for easier touch close */
                max-width: 280px; /* Maximum width */
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 50;
                box-shadow: 4px 0 12px rgba(0,0,0,0.3);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            /* Make sidebar more compact on mobile */
            .sidebar .p-4 {
                padding: 1rem !important;
            }

            .sidebar h1 {
                font-size: 1.25rem !important;
            }

            .sidebar .menu-item {
                padding: 0.625rem 1rem !important;
                font-size: 0.875rem !important;
            }

            .sidebar .menu-item i {
                font-size: 1rem !important;
            }

            .sidebar .section-header {
                padding: 0.5rem 1rem !important;
                font-size: 0.625rem !important;
            }

            /* Logout button more compact */
            .sidebar form button {
                padding: 0.625rem !important;
                font-size: 0.875rem !important;
            }

            /* Main content takes full width */
            .main-content {
                width: 100vw !important;
                max-width: 100vw !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .mobile-menu-button {
                display: flex !important;
                align-items: center;
                justify-content: center;
            }

            /* Ensure no flex layout issues */
            body {
                overflow-x: hidden;
            }

            .min-h-screen {
                min-height: 100vh;
                width: 100vw;
                overflow-x: hidden;
            }
        }
        @media (min-width: 769px) {
            .mobile-menu-button {
                display: none !important;
            }
        }

        /* Scroll to top button - mobile only */
        .scroll-to-top {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            cursor: pointer;
            z-index: 40;
            transition: all 0.3s ease;
            animation: bounceIn 0.5s;
        }

        .scroll-to-top.visible {
            display: flex;
        }

        .scroll-to-top:active {
            transform: scale(0.9);
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            .scroll-to-top {
                bottom: 1rem;
                right: 1rem;
                width: 2.75rem;
                height: 2.75rem;
            }
        }

        /* Improve touch targets for mobile */
        @media (max-width: 768px) {
            a, button {
                min-height: 44px; /* Apple's recommended minimum touch target */
                display: inline-flex;
                align-items: center;
            }

            /* Exception for small badges */
            .badge, span[class*="rounded-full"] {
                min-height: auto;
            }
        }

        /* Line clamp utility (Tailwind backup) */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Better word break for mobile */
        @media (max-width: 768px) {
            .break-words-mobile {
                word-break: break-word;
                overflow-wrap: break-word;
            }
        }

        /* Rotate utilities */
        .rotate-180 {
            transform: rotate(180deg);
        }

        .transition-transform {
            transition-property: transform;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 300ms;
        }
    </style>
</head>
<body class="bg-gray-100">
    @if(config('app.env') === 'staging')
    <!-- Staging Environment Warning Banner -->
    <div style="background: linear-gradient(90deg, #f97316, #dc2626); color: white; padding: 0.4rem 1rem; position: sticky; top: 0; z-index: 10000; font-size: 0.8rem;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
            <span><i class="fas fa-exclamation-triangle"></i> <strong>STAGING</strong></span>
            <span style="opacity: 0.7;">·</span>
            <span>Data is periodically deleted</span>
            <span style="opacity: 0.7;">·</span>
            @if(config('app.production_url'))
            <span>Production: <a href="{{ config('app.production_url') }}/admin" style="text-decoration: underline; color: #fef08a;" target="_blank">{{ parse_url(config('app.production_url'), PHP_URL_HOST) }}/admin</a></span>
            @endif
        </div>
    </div>
    @endif

    <!-- Mobile menu overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

    <!-- Scroll to top button -->
    <button class="scroll-to-top" id="scrollToTopBtn" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <div class="min-h-screen md:flex">
        <!-- Sidebar -->
        <div class="sidebar md:w-64 bg-gray-900 text-white flex flex-col h-screen md:sticky top-0" id="sidebar">
            <div class="p-4 flex-shrink-0">
                <h1 class="text-2xl font-bold flex items-center gap-3">
                    <img src="{{ asset('favicon-96x96.png') }}" alt="{{ config('site.name') }}" class="w-10 h-10 rounded-lg shadow-md">
                    <span>Admin Panel</span>
                </h1>
                <p class="text-sm text-gray-400 mt-1">{{ config('site.name') }} Moderation</p>
            </div>

            <div class="scroll-container flex-1 mt-4">
                <div class="scroll-shadow-top" id="scrollShadowTop"></div>
                <nav class="custom-scrollbar flex-1 overflow-y-auto">
                    <!-- Dashboard -->
                    <a href="{{ route('admin.dashboard') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.dashboard') ? 'active text-white' : '' }}">
                        <i class="fas fa-home mr-3"></i>
                        Dashboard
                    </a>

                    <div class="border-t border-gray-800 my-4"></div>

                    <!-- Moderación Section -->
                    <div class="section-header px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <i class="fas fa-shield-alt mr-2"></i>Moderación
                    </div>
                <a href="{{ route('admin.users') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.users*') ? 'active text-white' : '' }}">
                    <i class="fas fa-users mr-3"></i>
                    Users
                </a>
                <a href="{{ route('admin.posts') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.posts*') ? 'active text-white' : '' }}">
                    <i class="fas fa-file-alt mr-3"></i>
                    Posts
                </a>
                <a href="{{ route('admin.comments') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.comments*') ? 'active text-white' : '' }}">
                    <i class="fas fa-comments mr-3"></i>
                    Comments
                </a>
                <a href="{{ route('admin.images.index') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.images*') ? 'active text-white' : '' }}">
                    <i class="fas fa-image mr-3"></i>
                    Images
                </a>
                <a href="{{ route('admin.reports') }}" class="menu-item flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.reports') ? 'active text-white' : '' }}">
                    <span class="flex items-center">
                        <i class="fas fa-flag mr-3"></i>
                        Moderation Reports
                    </span>
                    @if(isset($menuCounters) && $menuCounters['pending_reports'] > 0)
                        <span class="px-2 py-0.5 text-xs font-semibold bg-red-500 text-white rounded-full">
                            {{ $menuCounters['pending_reports'] }}
                        </span>
                    @endif
                </a>
                <a href="{{ route('admin.legal-reports') }}" class="menu-item flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.legal-reports*') ? 'active text-white' : '' }}">
                    <span class="flex items-center">
                        <i class="fas fa-gavel mr-3"></i>
                        Legal Reports (DMCA)
                    </span>
                    @if(isset($menuCounters) && $menuCounters['pending_legal_reports'] > 0)
                        <span class="px-2 py-0.5 text-xs font-semibold bg-orange-500 text-white rounded-full">
                            {{ $menuCounters['pending_legal_reports'] }}
                        </span>
                    @endif
                </a>
                <a href="{{ route('admin.logs') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.logs*') ? 'active text-white' : '' }}">
                    <i class="fas fa-history mr-3"></i>
                    Moderation Logs
                </a>

                <!-- Spam & Abuse Collapsible Menu -->
                <div class="spam-abuse-menu">
                    <button onclick="toggleSpamAbuseMenu()" class="menu-item flex items-center justify-between w-full px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.spam-*') || request()->routeIs('admin.abuse*') ? 'active text-white' : '' }}">
                        <span class="flex items-center">
                            <i class="fas fa-shield-alt mr-3"></i>
                            Spam & Abuse
                        </span>
                        <i class="fas fa-chevron-down transition-transform duration-200" id="spamAbuseChevron"></i>
                    </button>

                    <div id="spamAbuseSubmenu" class="submenu {{ request()->routeIs('admin.spam-*') || request()->routeIs('admin.abuse*') ? '' : 'hidden' }}">
                        <a href="{{ route('admin.spam-detection') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.spam-detection') ? 'active text-white bg-gray-800' : '' }}">
                            <i class="fas fa-user-secret mr-3 text-xs"></i>
                            User Detection
                        </a>
                        <a href="{{ route('admin.spam-logs') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.spam-logs') ? 'active text-white bg-gray-800' : '' }}">
                            <i class="fas fa-clipboard-list mr-3 text-xs"></i>
                            Detection Logs
                        </a>
                        <a href="{{ route('admin.spam-configuration') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.spam-configuration') ? 'active text-white bg-gray-800' : '' }}">
                            <i class="fas fa-sliders-h mr-3 text-xs"></i>
                            Configuration
                        </a>
                        <a href="{{ route('admin.abuse') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.abuse*') ? 'active text-white bg-gray-800' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3 text-xs"></i>
                            Rate Limit Monitor
                        </a>
                    </div>
                </div>
                <a href="{{ route('admin.ip-blocks.index') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.ip-blocks*') ? 'active text-white' : '' }}">
                    <i class="fas fa-ban mr-3"></i>
                    IP Blocking
                </a>
                <a href="{{ route('admin.karma-configuration') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.karma-configuration') ? 'active text-white' : '' }}">
                    <i class="fas fa-trophy mr-3"></i>
                    Karma & Achievements
                </a>
                <a href="{{ route('admin.karma-history') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.karma-history') ? 'active text-white' : '' }}">
                    <i class="fas fa-history mr-3"></i>
                    Karma History
                </a>
                <a href="{{ route('admin.users.pending') }}" class="menu-item flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.users.pending') ? 'active text-white' : '' }}">
                    <span class="flex items-center">
                        <i class="fas fa-user-clock mr-3"></i>
                        Pending Users
                    </span>
                    @if(isset($menuCounters) && $menuCounters['pending_users'] > 0)
                        <span class="px-2 py-0.5 text-xs font-semibold bg-yellow-500 text-white rounded-full">
                            {{ $menuCounters['pending_users'] }}
                        </span>
                    @endif
                </a>

                    <div class="border-t border-gray-800 my-4"></div>

                    <!-- Administration Section -->
                    <div class="section-header px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <i class="fas fa-cogs mr-2"></i>Administration
                    </div>
                @can('admin-only')
                    <a href="{{ route('admin.settings') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.settings') ? 'active text-white' : '' }}">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    <a href="{{ route('admin.social') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.social*') ? 'active text-white' : '' }}">
                        <i class="fab fa-x-twitter mr-3"></i>
                        Social Media
                    </a>
                    <!-- ActivityPub / Federation Collapsible Menu -->
                    <div class="activitypub-menu">
                        <button onclick="toggleActivityPubMenu()" class="menu-item flex items-center justify-between w-full px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.activitypub*') || request()->routeIs('admin.federation*') ? 'active text-white' : '' }}">
                            <span class="flex items-center">
                                <i class="fab fa-mastodon mr-3"></i>
                                ActivityPub
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" id="activityPubChevron"></i>
                        </button>

                        <div id="activityPubSubmenu" class="submenu {{ request()->routeIs('admin.activitypub*') || request()->routeIs('admin.federation*') ? '' : 'hidden' }}">
                            <a href="{{ route('admin.activitypub') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.activitypub') && !request()->routeIs('admin.activitypub.*') ? 'active text-white bg-gray-800' : '' }}">
                                <i class="fas fa-paper-plane mr-3 text-xs"></i>
                                Deliveries
                            </a>
                            <a href="{{ route('admin.federation.blocked') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.federation.blocked*') ? 'active text-white bg-gray-800' : '' }}">
                                <i class="fas fa-ban mr-3 text-xs"></i>
                                Blocked Instances
                            </a>
                            <a href="{{ route('admin.federation.stats') }}" class="submenu-item flex items-center pl-12 pr-6 py-2.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.federation.stats') ? 'active text-white bg-gray-800' : '' }}">
                                <i class="fas fa-chart-bar mr-3 text-xs"></i>
                                Statistics
                            </a>
                        </div>
                    </div>
                    <a href="{{ route('admin.database') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.database') ? 'active text-white' : '' }}">
                        <i class="fas fa-database mr-3"></i>
                        Database
                    </a>
                    <a href="{{ route('admin.scheduled-commands') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.scheduled-commands') ? 'active text-white' : '' }}">
                        <i class="fas fa-terminal mr-3"></i>
                        Scheduled Commands
                    </a>
                    <a href="{{ route('admin.system-status') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.system-status') ? 'active text-white' : '' }}">
                        <i class="fas fa-heartbeat mr-3"></i>
                        System Status
                    </a>
                    <a href="{{ route('admin.error-logs') }}" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.error-logs*') ? 'active text-white' : '' }}">
                        <i class="fas fa-bug mr-3"></i>
                        Error Logs
                    </a>
                    <a href="{{ url('/telescope') }}" target="_blank" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white">
                        <i class="fas fa-microscope mr-3"></i>
                        Telescope
                        <i class="fas fa-external-link-alt ml-2 text-xs"></i>
                    </a>
                @else
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-images mr-3"></i>
                        Image Settings
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-database mr-3"></i>
                        Database
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-terminal mr-3"></i>
                        Scheduled Commands
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-heartbeat mr-3"></i>
                        System Status
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-2 text-yellow-500"></i>
                        <i class="fas fa-bug mr-3"></i>
                        Error Logs
                    </a>
                    <a href="#" class="admin-locked flex items-center px-6 py-3 text-gray-500 cursor-not-allowed opacity-60" data-requires="admin">
                        <i class="fas fa-lock mr-3 text-yellow-500"></i>
                        Telescope
                    </a>
                @endcannot

                    <div class="border-t border-gray-800 my-4"></div>

                    <form action="{{ route('admin.logout') }}" method="POST" class="px-6 py-3">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center px-4 py-2.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium shadow-sm">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </form>

                    <a href="/" class="menu-item flex items-center px-6 py-3 text-gray-300 hover:bg-gray-800 hover:text-white">
                        <i class="fas fa-arrow-left mr-3"></i>
                        Back to Site
                    </a>
                </nav>
                <div class="scroll-shadow-bottom" id="scrollShadowBottom"></div>
            </div>

            <!-- Collapse button (desktop only) -->
            <div id="sidebarCollapseContainer" class="sidebar-collapse-container" style="background: #1f2937 !important; width: 100% !important; position: relative !important; z-index: 9999 !important; border-top: 1px solid #374151 !important;">
                <button onclick="toggleSidebar();" class="sidebar-collapse-btn" onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#1f2937'" style="width: 100% !important; background: #1f2937 !important; color: #d1d5db !important; padding: 12px !important; font-size: 14px !important; font-weight: 500 !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; border: none !important; transition: all 0.2s !important;">
                    <i class="fas fa-angles-left sidebar-collapse-icon" style="color: #d1d5db !important; font-size: 16px !important; margin-right: 8px !important;"></i>
                    <span class="sidebar-collapse-text" style="color: #d1d5db !important;">Collapse</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between px-6 md:px-8 py-1.5 md:py-4">
                    <!-- Left side: Mobile menu button + Page Title -->
                    <div class="flex items-center space-x-3 flex-1 min-w-0">
                        <!-- Mobile menu button (left side) -->
                        <button onclick="toggleMobileMenu();" class="md:hidden flex items-center justify-center w-8 h-8 text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all">
                            <i class="fas fa-bars text-base"></i>
                        </button>

                        <!-- Page Title -->
                        <h2 class="text-sm md:text-2xl font-semibold text-gray-800 truncate">
                            @yield('page-title', 'Dashboard')
                        </h2>
                    </div>

                    <!-- Right side: User Menu -->
                    <div class="relative">
                            <button onclick="toggleUserMenu()" class="flex items-center space-x-2 md:space-x-3 hover:opacity-80 transition-opacity focus:outline-none">
                                <div class="text-right hidden md:block">
                                    <div class="text-sm font-semibold text-gray-900">{{ auth()->user()->username }}</div>
                                    <div class="text-xs text-gray-500">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ auth()->user()->hasRole('admin') ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                            <i class="fas fa-{{ auth()->user()->hasRole('admin') ? 'crown' : 'shield-alt' }} mr-1"></i>
                                            {{ auth()->user()->hasRole('admin') ? 'Administrator' : 'Moderator' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="h-8 w-8 md:h-10 md:w-10 rounded-full flex items-center justify-center overflow-hidden shadow-lg bg-gray-100">
                                    @if(auth()->user()->avatar)
                                        <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->username }}" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user text-gray-400 text-sm md:text-base"></i>
                                    @endif
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 z-50">
                                <div class="py-2">
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-semibold text-gray-900 mb-2">{{ auth()->user()->username }}</p>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ auth()->user()->hasRole('admin') ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                            <i class="fas fa-{{ auth()->user()->hasRole('admin') ? 'crown' : 'shield-alt' }} mr-1.5"></i>
                                            {{ auth()->user()->hasRole('admin') ? 'Administrator' : 'Moderator' }}
                                        </span>
                                    </div>
                                    <a href="/" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-arrow-left mr-3 text-gray-400"></i>
                                        Back to Site
                                    </a>
                                    <form action="{{ route('admin.logout') }}" method="POST" class="border-t border-gray-100">
                                        @csrf
                                        <button type="submit" class="w-full flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                            <i class="fas fa-sign-out-alt mr-3"></i>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            @if(session('success'))
                <div class="mx-3 md:mx-8 mt-3 md:mt-4 bg-green-50 border border-green-200 text-green-800 px-3 md:px-4 py-2 md:py-3 rounded-lg text-sm md:text-base shadow-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mx-3 md:mx-8 mt-3 md:mt-4 bg-red-50 border border-red-200 text-red-800 px-3 md:px-4 py-2 md:py-3 rounded-lg text-sm md:text-base shadow-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Page Content -->
            <main class="flex-1 p-3 md:p-8 overflow-auto">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    <!-- Admin Access Required Modal -->
    <div id="adminAccessModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-lock text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Restricted Access</h3>
                </div>
                <p class="text-gray-600 mb-6">
                    This section requires administrator permissions. Contact an administrator if you need access.
                </p>
                <div class="flex justify-end">
                    <button onclick="closeAdminModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check mr-2"></i>Got it
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reusable Confirm Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div id="confirmModalIcon" class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-question text-yellow-600 text-xl"></i>
                    </div>
                    <h3 id="confirmModalTitle" class="text-xl font-semibold text-gray-900">Confirm Action</h3>
                </div>
                <p id="confirmModalMessage" class="text-gray-600 mb-6"></p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="confirmModalBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check mr-2"></i>Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle - define FIRST so it's available immediately
        function toggleMobileMenu(forceClose = false) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileMenuOverlay');
            const isOpen = sidebar.classList.contains('mobile-open');

            if (forceClose || isOpen) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        // Handle clicks on admin-locked items
        document.addEventListener('DOMContentLoaded', function() {
            const lockedItems = document.querySelectorAll('.admin-locked');
            lockedItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('adminAccessModal').classList.remove('hidden');
                });
            });

            // Auto-wrap tables in responsive container for mobile
            function makeTablesResponsive() {
                if (window.innerWidth <= 768) {
                    const tables = document.querySelectorAll('table');
                    tables.forEach(table => {
                        // Check if not already wrapped
                        const parent = table.parentElement;
                        if (!parent.classList.contains('table-responsive')) {
                            // Create wrapper
                            const wrapper = document.createElement('div');
                            wrapper.classList.add('table-responsive');
                            wrapper.style.cssText = 'overflow-x: auto !important; -webkit-overflow-scrolling: touch; width: 100%; display: block;';

                            // Wrap the table
                            parent.insertBefore(wrapper, table);
                            wrapper.appendChild(table);

                            // Add scroll indicator
                            checkScrollIndicator(wrapper);
                            wrapper.addEventListener('scroll', () => checkScrollIndicator(wrapper));
                        }
                    });

                    // Force overflow on all containers with tables
                    document.querySelectorAll('.bg-white').forEach(card => {
                        if (card.querySelector('table')) {
                            card.style.overflowX = 'auto';
                            card.style.WebkitOverflowScrolling = 'touch';

                            // Add scroll indicator for cards
                            checkScrollIndicator(card);
                            card.addEventListener('scroll', () => checkScrollIndicator(card));
                        }
                    });

                    // Enable smooth touch scrolling for tables
                    document.querySelectorAll('table, .table-responsive').forEach(element => {
                        element.style.touchAction = 'pan-x pan-y';
                    });
                }
            }

            // Check if element can scroll and add indicator
            function checkScrollIndicator(element) {
                if (element.scrollWidth > element.clientWidth) {
                    element.classList.add('can-scroll');
                } else {
                    element.classList.remove('can-scroll');
                }
            }

            // Call on load
            makeTablesResponsive();

            // Also call after delays to catch dynamically loaded content
            setTimeout(makeTablesResponsive, 500);
            setTimeout(makeTablesResponsive, 1500);

            // Recalculate on window resize
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(makeTablesResponsive, 250);
            });

            // Scroll shadows
            const scrollContainer = document.querySelector('.custom-scrollbar');
            const shadowTop = document.getElementById('scrollShadowTop');
            const shadowBottom = document.getElementById('scrollShadowBottom');

            if (scrollContainer && shadowTop && shadowBottom) {
                function updateScrollShadows() {
                    const scrollTop = scrollContainer.scrollTop;
                    const scrollHeight = scrollContainer.scrollHeight;
                    const clientHeight = scrollContainer.clientHeight;
                    const scrollBottom = scrollHeight - scrollTop - clientHeight;

                    // Show top shadow if scrolled down
                    if (scrollTop > 10) {
                        shadowTop.classList.add('visible');
                    } else {
                        shadowTop.classList.remove('visible');
                    }

                    // Show bottom shadow if not at bottom
                    if (scrollBottom > 10) {
                        shadowBottom.classList.add('visible');
                    } else {
                        shadowBottom.classList.remove('visible');
                    }
                }

                scrollContainer.addEventListener('scroll', updateScrollShadows);
                updateScrollShadows(); // Initial check
            }
        });

        function closeAdminModal() {
            document.getElementById('adminAccessModal').classList.add('hidden');
        }

        // Confirm modal functions
        let confirmModalCallback = null;
        let confirmModalForm = null;

        function showConfirmModal(message, options = {}) {
            const modal = document.getElementById('confirmModal');
            const titleEl = document.getElementById('confirmModalTitle');
            const messageEl = document.getElementById('confirmModalMessage');
            const iconEl = document.getElementById('confirmModalIcon');
            const btnEl = document.getElementById('confirmModalBtn');

            // Set content
            titleEl.textContent = options.title || 'Confirm Action';
            messageEl.textContent = message;

            // Set icon style based on type
            const type = options.type || 'warning';
            iconEl.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4';
            if (type === 'danger') {
                iconEl.classList.add('bg-red-100');
                iconEl.innerHTML = '<i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>';
                btnEl.className = 'px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors';
            } else {
                iconEl.classList.add('bg-yellow-100');
                iconEl.innerHTML = '<i class="fas fa-question text-yellow-600 text-xl"></i>';
                btnEl.className = 'px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors';
            }

            // Set button text
            btnEl.innerHTML = '<i class="fas fa-check mr-2"></i>' + (options.confirmText || 'Confirm');

            // Store callback or form
            confirmModalCallback = options.onConfirm || null;
            confirmModalForm = options.form || null;

            modal.classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            confirmModalCallback = null;
            confirmModalForm = null;
        }

        function handleConfirmModal() {
            if (confirmModalForm) {
                confirmModalForm.submit();
            } else if (confirmModalCallback) {
                confirmModalCallback();
            }
            closeConfirmModal();
        }

        // Attach confirm handler
        document.addEventListener('DOMContentLoaded', function() {
            const confirmBtn = document.getElementById('confirmModalBtn');
            if (confirmBtn) {
                confirmBtn.onclick = handleConfirmModal;
            }

            // Close confirm modal when clicking outside
            document.getElementById('confirmModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeConfirmModal();
                }
            });
        });

        // Helper function for forms with data-confirm attribute
        function confirmSubmit(form, message, options = {}) {
            showConfirmModal(message, { ...options, form: form });
            return false;
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('adminAccessModal');
            if (e.target === modal) {
                closeAdminModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminModal();
                closeConfirmModal();
                closeUserMenu();
            }
        });

        // User menu dropdown
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        }

        function closeUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.add('hidden');
        }

        // Toggle sidebar collapse (desktop only)
        function toggleSidebar() {
            const body = document.body;
            const collapseIcon = document.querySelector('.sidebar-collapse-icon');
            const collapseText = document.querySelector('.sidebar-collapse-text');
            const collapseBtn = document.querySelector('.sidebar-collapse-btn');

            body.classList.toggle('sidebar-collapsed');

            // Save state in localStorage
            if (body.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarCollapsed', 'true');
                if (collapseIcon) {
                    collapseIcon.classList.remove('fa-angles-left');
                    collapseIcon.classList.add('fa-angles-right');
                }
                if (collapseText) {
                    collapseText.textContent = 'Expand Sidebar';
                }
                if (collapseBtn) {
                    collapseBtn.setAttribute('title', 'Expand sidebar');
                }
            } else {
                localStorage.setItem('sidebarCollapsed', 'false');
                if (collapseIcon) {
                    collapseIcon.classList.remove('fa-angles-right');
                    collapseIcon.classList.add('fa-angles-left');
                }
                if (collapseText) {
                    collapseText.textContent = 'Collapse Sidebar';
                }
                if (collapseBtn) {
                    collapseBtn.setAttribute('title', 'Collapse sidebar');
                }
            }
        }

        // Hide collapse button on mobile, restore state on desktop
        document.addEventListener('DOMContentLoaded', function() {
            const collapseContainer = document.getElementById('sidebarCollapseContainer');

            // Force hide on mobile
            if (window.innerWidth < 768) {
                if (collapseContainer) {
                    collapseContainer.style.display = 'none';
                }
                // Remove collapsed class on mobile
                document.body.classList.remove('sidebar-collapsed');
            } else {
                // Only apply collapsed state on desktop (>= 768px)
                if (collapseContainer) {
                    collapseContainer.style.display = 'block';
                }

                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                const collapseIcon = document.querySelector('.sidebar-collapse-icon');
                const collapseText = document.querySelector('.sidebar-collapse-text');
                const collapseBtn = document.querySelector('.sidebar-collapse-btn');

                if (isCollapsed) {
                    document.body.classList.add('sidebar-collapsed');
                    if (collapseIcon) {
                        collapseIcon.classList.remove('fa-angles-left');
                        collapseIcon.classList.add('fa-angles-right');
                    }
                    if (collapseText) {
                        collapseText.textContent = 'Expand Sidebar';
                    }
                    if (collapseBtn) {
                        collapseBtn.setAttribute('title', 'Expand sidebar');
                    }
                }
            }
        });

        // Close user menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('userMenu');
            const button = e.target.closest('button[onclick="toggleUserMenu()"]');

            if (!button && !menu.contains(e.target)) {
                closeUserMenu();
            }
        });

        // Close mobile menu when clicking a link in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const menuLinks = document.querySelectorAll('.sidebar a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        toggleMobileMenu(true);
                    }
                });
            });
        });

        // Smart overlay click detection - only close on real clicks, not drags/scrolls
        document.addEventListener('DOMContentLoaded', function() {
            let touchStartX = 0;
            let touchStartY = 0;
            let isTouchMoving = false;

            const overlay = document.getElementById('mobileMenuOverlay');

            overlay.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                isTouchMoving = false;
            }, { passive: true });

            overlay.addEventListener('touchmove', function(e) {
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                const deltaX = Math.abs(touchX - touchStartX);
                const deltaY = Math.abs(touchY - touchStartY);

                // If moved more than 5px, it's a scroll/drag, not a click
                if (deltaX > 5 || deltaY > 5) {
                    isTouchMoving = true;
                }
            }, { passive: true });

            overlay.addEventListener('touchend', function(e) {
                // Only close menu if it was a tap, not a drag/scroll
                if (!isTouchMoving) {
                    toggleMobileMenu(true); // Force close
                }
            }, { passive: true });

            // Also handle mouse clicks for desktop testing
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    toggleMobileMenu(true); // Force close
                }
            });

            // Scroll to top button functionality
            const scrollToTopBtn = document.getElementById('scrollToTopBtn');
            const mainContent = document.querySelector('main');

            // Show/hide scroll to top button
            function toggleScrollToTop() {
                if (mainContent.scrollTop > 300) {
                    scrollToTopBtn.classList.add('visible');
                } else {
                    scrollToTopBtn.classList.remove('visible');
                }
            }

            mainContent.addEventListener('scroll', toggleScrollToTop);

            // Scroll to top on click
            scrollToTopBtn.addEventListener('click', function() {
                mainContent.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });

        // Toggle Spam & Abuse submenu
        function toggleSpamAbuseMenu() {
            const submenu = document.getElementById('spamAbuseSubmenu');
            const chevron = document.getElementById('spamAbuseChevron');

            submenu.classList.toggle('hidden');

            if (submenu.classList.contains('hidden')) {
                chevron.style.transform = 'rotate(0deg)';
                localStorage.setItem('spamAbuseMenuOpen', 'false');
            } else {
                chevron.style.transform = 'rotate(180deg)';
                localStorage.setItem('spamAbuseMenuOpen', 'true');
            }
        }

        // Toggle ActivityPub submenu
        function toggleActivityPubMenu() {
            const submenu = document.getElementById('activityPubSubmenu');
            const chevron = document.getElementById('activityPubChevron');

            submenu.classList.toggle('hidden');

            if (submenu.classList.contains('hidden')) {
                chevron.style.transform = 'rotate(0deg)';
                localStorage.setItem('activityPubMenuOpen', 'false');
            } else {
                chevron.style.transform = 'rotate(180deg)';
                localStorage.setItem('activityPubMenuOpen', 'true');
            }
        }

        // Restore submenu state on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Spam & Abuse submenu
            const submenu = document.getElementById('spamAbuseSubmenu');
            const chevron = document.getElementById('spamAbuseChevron');
            const isOpen = localStorage.getItem('spamAbuseMenuOpen') === 'true';

            // If we're on a spam/abuse page, keep it open regardless
            const isOnSpamAbusePage = submenu && !submenu.classList.contains('hidden');

            if (isOpen || isOnSpamAbusePage) {
                if (submenu) submenu.classList.remove('hidden');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }

            // ActivityPub submenu
            const apSubmenu = document.getElementById('activityPubSubmenu');
            const apChevron = document.getElementById('activityPubChevron');
            const isApOpen = localStorage.getItem('activityPubMenuOpen') === 'true';

            // If we're on an activitypub/federation page, keep it open regardless
            const isOnApPage = apSubmenu && !apSubmenu.classList.contains('hidden');

            if (isApOpen || isOnApPage) {
                if (apSubmenu) apSubmenu.classList.remove('hidden');
                if (apChevron) apChevron.style.transform = 'rotate(180deg)';
            }
        });
    </script>
</body>
</html>
