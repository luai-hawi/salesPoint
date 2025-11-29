<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luai Hawi - Portfolio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-100px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }

            50% {
                transform: scale(1.05);
            }

            70% {
                transform: scale(0.9);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes rotateIn {
            from {
                opacity: 0;
                transform: rotate(-10deg) scale(0.8);
            }

            to {
                opacity: 1;
                transform: rotate(0) scale(1);
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes slideCarousel {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
        }

        .slide-in-left {
            animation: slideInLeft 0.8s ease-out forwards;
            opacity: 0;
        }

        .slide-in-right {
            animation: slideInRight 0.8s ease-out forwards;
            opacity: 0;
        }

        .bounce-in {
            animation: bounceIn 0.8s ease-out forwards;
            opacity: 0;
        }

        .rotate-in {
            animation: rotateIn 0.8s ease-out forwards;
            opacity: 0;
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-15px) rotate(2deg);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        .img-zoom {
            overflow: hidden;
        }

        .img-zoom img {
            transition: transform 0.6s ease;
        }

        .img-zoom:hover img {
            transform: scale(1.2) rotate(3deg);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .carousel-container {
            overflow: hidden;
            position: relative;
        }

        .carousel-track {
            display: flex;
            animation: slideCarousel 20s linear infinite;
        }

        .carousel-track:hover {
            animation-play-state: paused;
        }

        .carousel-item {
            flex: 0 0 33.333%;
            padding: 0 10px;
        }

        @media (max-width: 768px) {
            .carousel-item {
                flex: 0 0 100%;
            }
        }

        .stagger-1 {
            animation-delay: 0.1s;
        }

        .stagger-2 {
            animation-delay: 0.3s;
        }

        .stagger-3 {
            animation-delay: 0.5s;
        }

        .stagger-4 {
            animation-delay: 0.7s;
        }

        .stagger-5 {
            animation-delay: 0.9s;
        }

        .stagger-6 {
            animation-delay: 1.1s;
        }

        .text-gradient {
            background: linear-gradient(45deg, #667eea, #764ba2, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .tag-float {
            transition: all 0.3s ease;
        }

        .tag-float:hover {
            transform: translateY(-5px) scale(1.1);
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
            }

            50% {
                box-shadow: 0 0 40px rgba(102, 126, 234, 0.8);
            }
        }

        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-gray-50 overflow-x-hidden">

    <!-- Hero Section -->
    <div class="gradient-bg text-white py-20 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-10 left-10 w-72 h-72 bg-white rounded-full blur-3xl float-animation"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-purple-300 rounded-full blur-3xl float-animation"
                style="animation-delay: 1s;"></div>
        </div>
        <div class="container mx-auto px-6 relative z-10">
            <h1 class="text-6xl font-bold mb-4 bounce-in">Luai Hawi</h1>
            <p class="text-2xl slide-in-left stagger-1">Computer Engineer | Full Stack Developer | Robotics Enthusiast
            </p>
            <p class="mt-4 text-lg slide-in-right stagger-2">Transforming ideas into innovative software solutions</p>
            <div class="mt-8 flex gap-4 fade-in-up stagger-3">
            </div>
        </div>
    </div>

    <!-- Projects Section -->
    <div class="container mx-auto px-6 py-16">
        <h2 class="text-5xl font-bold text-center mb-4 text-gradient bounce-in">Featured Projects</h2>
        <p class="text-center text-gray-600 mb-16 fade-in-up stagger-1">Innovative solutions that make a difference</p>

        <!-- Style 1: Card Grid with Enhanced Animations -->
        <div class="mb-24">
            <h3 class="text-3xl font-semibold mb-8 text-gray-700 border-l-4 border-purple-600 pl-4 slide-in-left">
                Enterprise Solutions</h3>
            <div class="grid md:grid-cols-2 gap-8">

                <!-- Project 1 -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift rotate-in stagger-1">
                    <!-- Auto-Sliding Images -->
                    <div class="carousel-container h-64">
                        <div class="carousel-track">
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale1.jpg" alt="ERP System - Dashboard"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale2.jpg" alt="ERP System - POS"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale3.jpg" alt="ERP System - Reports"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale4.jpg" alt="ERP System - Reports"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale5.jpg" alt="ERP System - Reports"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale1.jpg" alt="ERP System - Dashboard"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale2.jpg" alt="ERP System - POS"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale3.jpg" alt="ERP System - POS"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale4.jpg" alt="ERP System - POS"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/sale5.jpg" alt="ERP System - POS"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <h4 class="text-2xl font-bold mb-3 text-gray-800">ERP System with POS Module</h4>
                        <div id="" style="overflow-y:scroll; height:200px;">
                            <p class="text-gray-600 mb-4">Engineered a comprehensive enterprise resource planning system
                                integrated with a point-of-sale module, leveraging Laravel's robust MVC architecture,
                                PHP
                                backend processing, and Tailwind CSS for a modern, responsive interface. The system
                                features
                                an advanced role-based access control mechanism that provides granular permission
                                management
                                at the page level, enabling administrators to precisely define what actions each user
                                can
                                perform across the entire platform. Implemented sophisticated product management
                                capabilities supporting duplicate barcodes for handling multiple suppliers of identical
                                items, comprehensive billing operations with invoice generation and payment tracking,
                                and a
                                complete purchase order management system for supplier coordination. Built automated
                                workflows for routine business processes including inventory alerts, payment reminders,
                                and
                                report generation. Developed an intuitive financial dashboard that aggregates data from
                                multiple sources to provide real-time insights into revenue streams, expense patterns,
                                profit margins, and cash flow analysis. The system includes robust expense and salary
                                tracking modules with approval workflows, customer relationship management with purchase
                                history and credit management, and supplier management with performance metrics and
                                payment
                                scheduling. Designed with scalability in mind to accommodate growing business needs
                                while
                                maintaining optimal performance and data security.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm tag-float">Laravel</span>
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm tag-float">PHP</span>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm tag-float">Tailwind
                                CSS</span>
                        </div>
                    </div>
                </div>

                <!-- Project 2 -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift rotate-in stagger-2">
                    <!-- Auto-Sliding Images -->
                    <div class="carousel-container h-64">
                        <div class="carousel-track" style="animation-duration: 22s;">
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu1.jpg" alt="Restaurant Menu - Main"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu2.jpg" alt="Restaurant Menu - Dashboard"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu3.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu4.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu5.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu1.jpg" alt="Restaurant Menu - Main"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu2.jpg" alt="Restaurant Menu - Dashboard"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu3.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu4.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-64">
                                    <img src="images/menu5.jpg" alt="Restaurant Menu - Customization"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <h4 class="text-2xl font-bold mb-3 text-gray-800">Restaurant Menu Management System</h4>
                        <div id="" style="overflow-y:scroll; height:200px;">
                            <p class="text-gray-600 mb-4">Developed a sophisticated web-based platform that
                                revolutionizes
                                how restaurants manage their digital menu presence, built with Laravel backend
                                architecture
                                and modern frontend technologies. The system implements a comprehensive multi-level
                                role-based access control framework, allowing restaurant owners to create custom
                                permission
                                hierarchies for managers, staff, and content editors with granular control over who can
                                view, edit, or publish menu content. Created an intuitive manager dashboard that serves
                                as a
                                central command center for all menu operations, enabling seamless management of
                                categories,
                                subcategories, and individual menu items with support for detailed descriptions, pricing
                                variations, dietary information, and allergen warnings. Implemented a powerful
                                customization
                                engine that gives restaurant owners complete control over their menu's visual identity,
                                including dynamic color scheme management, typography selection, layout templates, and
                                brand
                                element integration. The platform supports real-time preview functionality, allowing
                                managers to see exactly how changes will appear to customers before publishing. Built-in
                                image optimization ensures fast loading times while maintaining visual quality across
                                all
                                devices. The system also includes inventory integration capabilities to automatically
                                mark
                                items as unavailable when ingredients run low, special promotion management for
                                time-limited
                                offers, and multilingual support for international establishments. Designed with
                                mobile-first principles to ensure the menu displays beautifully on smartphones, tablets,
                                and
                                desktop devices.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm tag-float">Laravel</span>
                            <span
                                class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm tag-float">JavaScript</span>
                            <span
                                class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm tag-float">Responsive
                                Design</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Style 2: Alternating Layout with Enhanced Movements -->
        <div class="mb-24">
            <h3 class="text-3xl font-semibold mb-8 text-gray-700 border-l-4 border-blue-600 pl-4 slide-in-right">
                Robotics & Automation</h3>

            <!-- Project 3 - Image Left -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8 slide-in-left stagger-1 hover-lift">
                <div class="md:flex">
                    <div class="md:w-1/2 carousel-container">
                        <div class="carousel-track" style="animation-duration: 18s;">
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros1.jpg" alt="Robot - Overview"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros2.jpg" alt="Robot - LiDAR"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros3.jpg" alt="Robot - Interface"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros4.jpg" alt="Robot - Interface"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros1.jpg" alt="Robot - Overview"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros2.jpg" alt="Robot - LiDAR"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros3.jpg" alt="Robot - Interface"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/ros4.jpg" alt="Robot - Interface"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-8 flex flex-col justify-center">
                        <h4 class="text-3xl font-bold mb-4 text-gray-800 fade-in-up">Autonomous Robot using ROS2</h4>
                        <div id="" style="overflow-y:scroll; height:200px;">
                            <p class="text-gray-600 mb-4 fade-in-up stagger-1">Spearheaded the complete development
                                lifecycle of an autonomous mobile robot system utilizing the Robot Operating System 2
                                (ROS2)
                                framework, combining advanced hardware integration with sophisticated software
                                algorithms.
                                Integrated a 360-degree LiDAR sensor system capable of generating detailed environmental
                                point clouds in real-time, enabling the robot to construct comprehensive maps of its
                                surroundings while simultaneously tracking its own position. Implemented Simultaneous
                                Localization and Mapping (SLAM) algorithms that process LiDAR data in conjunction with
                                high-precision motor encoder feedback to achieve accurate localization with
                                centimeter-level
                                precision, even in dynamic environments with moving obstacles. Developed a computer
                                vision
                                pipeline using camera inputs for enhanced object recognition and classification,
                                enabling
                                the robot to identify specific objects, read signage, and navigate based on visual
                                landmarks. Architected the system using ROS2's distributed computing paradigm, creating
                                modular nodes for sensor processing, navigation planning, motor control, and user
                                interface
                                management, all communicating through optimized topic subscriptions and publications for
                                minimal latency. Designed and implemented a custom touchscreen interface that provides
                                intuitive command input, real-time status monitoring, system diagnostics, and manual
                                override capabilities. The interface displays live sensor data visualizations,
                                navigation
                                path planning, and environmental mapping updates. Integrated safety features including
                                emergency stop protocols, collision avoidance systems, and fail-safe mechanisms. The
                                complete system demonstrates autonomous navigation capabilities in complex indoor
                                environments, adaptive path planning around obstacles, and seamless integration of
                                multiple
                                sensor modalities for robust operation.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-2">ROS2</span>
                            <span
                                class="bg-cyan-100 text-cyan-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-3">C++</span>
                            <span
                                class="bg-teal-100 text-teal-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-4">Python</span>
                            <span
                                class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-5">Computer
                                Vision</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project 4 - Image Right -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden slide-in-right stagger-2 hover-lift">
                <div class="md:flex md:flex-row-reverse">
                    <div class="md:w-1/2 carousel-container">
                        <div class="carousel-track" style="animation-duration: 24s;">
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app1.jpg" alt="Market App - Main"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app2.jpg" alt="Market App - Data Entry"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app3.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app4.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app5.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app6.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app7.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app1.jpg" alt="Market App - Main"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app2.jpg" alt="Market App - Data Entry"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app3.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app4.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app5.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app6.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom h-full">
                                    <img src="images/app7.jpg" alt="Market App - Sync"
                                        class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-8 flex flex-col justify-center">
                        <h4 class="text-3xl font-bold mb-4 text-gray-800 fade-in-up">Market Auction Data Collection App
                        </h4>
                        <div id="" style="overflow-y:scroll; height:200px;">
                            <p class="text-gray-600 mb-4 fade-in-up stagger-1">Developed a transformative Android
                                application that modernized the operational workflow at the Central Vegetables and
                                Fruits
                                Market in Jericho by completely eliminating paper-based auction data recording
                                processes.
                                The application addresses a critical pain point where auctioneers had to manually write
                                extensive auction details on paper forms during fast-paced bidding sessions, followed by
                                hours of manual data entry into the market's computer system after closing. Engineered
                                an
                                intuitive mobile interface optimized for rapid data input during live auctions,
                                featuring
                                large touch targets, numeric keypad optimization, and voice input support for hands-free
                                operation. Implemented intelligent API synchronization that maintains a local database
                                on
                                the device, allowing auctioneers to continue recording data even during network
                                interruptions, with automatic conflict resolution when connectivity is restored.
                                Developed a
                                batch upload system that efficiently transmits accumulated auction records to the
                                central
                                server in a single operation after market hours, significantly reducing server load and
                                ensuring data integrity. The application includes a powerful search functionality with
                                multi-criteria filtering, enabling quick lookup of specific auctions or products during
                                the
                                busy market day. Created a streamlined editing interface that allows rapid correction of
                                data entry mistakes with visual confirmation and undo capabilities, crucial for handling
                                the
                                chaotic situations that frequently arise in auction environments. Implemented validation
                                rules to catch common errors before submission, reducing downstream data quality issues.
                                The
                                system has delivered measurable business impact, cutting administrative overhead by more
                                than 2 hours daily, eliminating transcription errors that previously caused financial
                                discrepancies, and enabling auction staff to leave immediately after market closure
                                rather
                                than staying late for data entry. The app's efficiency improvements have allowed the
                                market
                                to reallocate staff resources to customer service and quality control functions.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-2">Android</span>
                            <span
                                class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-3">Java</span>
                            <span
                                class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm tag-float bounce-in stagger-4">API
                                Integration</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Style 3: Full Width with Auto-Sliding Carousel -->
        <div class="mb-24">
            <h3 class="text-3xl font-semibold mb-8 text-gray-700 border-l-4 border-green-600 pl-4 bounce-in">AI &
                Machine Learning</h3>

            <!-- Project 5 with Auto-Sliding Gallery -->
            <div
                class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl shadow-2xl overflow-hidden text-white fade-in-up stagger-1">
                <div class="p-8 md:p-12">
                    <h4 class="text-4xl font-bold mb-4 slide-in-left">Handwritten Letters Recognition System</h4>
                    <div id="" style="overflow-y:scroll; height:200px; scrollbar-color: #484852 #bbda5500;">
                        <p class="text-gray-300 mb-6 text-lg slide-in-right stagger-1">Developed a sophisticated deep
                            learning system for handwritten character recognition using Convolutional Neural Network
                            (CNN)
                            architecture, leveraging TensorFlow and Keras frameworks for model development and training.
                            Designed a multi-layer neural network architecture featuring convolutional layers for
                            feature
                            extraction, pooling layers for dimensionality reduction, dropout layers for regularization
                            to
                            prevent overfitting, and fully connected layers for classification. Preprocessed training
                            datasets including image normalization, augmentation techniques such as rotation and scaling
                            to
                            improve model robustness, and noise injection to simulate real-world handwriting variations.
                            Implemented advanced training strategies including learning rate scheduling, early stopping
                            based on validation performance, and checkpoint saving to preserve optimal model states.
                            Achieved high classification accuracy through iterative hyperparameter tuning and
                            architecture
                            optimization. Developed a real-time prediction interface that accepts handwritten input
                            through
                            various methods including touchscreen drawing, stylus input, and uploaded images, with
                            immediate
                            classification results and confidence scores. The system includes visualization tools to
                            display
                            the model's decision-making process, showing which features in the input image contributed
                            most
                            strongly to the classification decision. Built comprehensive testing and validation
                            frameworks
                            to evaluate model performance across different handwriting styles, ensuring robust operation
                            with diverse user inputs. Integrated error analysis tools to identify misclassification
                            patterns
                            and guide further model improvements. The application demonstrates practical use cases in
                            form
                            processing automation, educational tools for handwriting practice with instant feedback, and
                            accessibility features for digitizing handwritten documents.</p>
                    </div>

                    <!-- Auto-Sliding Image Carousel -->
                    <div class="carousel-container mt-8 mb-6 rounded-lg">
                        <div class="carousel-track">
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1555949963-aa79dcee981c?w=400&h=300&fit=crop"
                                        alt="CNN Architecture" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&h=300&fit=crop"
                                        alt="Training Results" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=400&h=300&fit=crop"
                                        alt="Live Demo" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1555949963-aa79dcee981c?w=400&h=300&fit=crop"
                                        alt="CNN Architecture" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&h=300&fit=crop"
                                        alt="Training Results" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=400&h=300&fit=crop"
                                        alt="Live Demo" class="w-full h-48 object-cover">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-6">
                        <span
                            class="bg-red-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-2">Python</span>
                        <span
                            class="bg-yellow-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-3">TensorFlow</span>
                        <span
                            class="bg-blue-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-4">CNN</span>
                        <span
                            class="bg-green-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-5">Machine
                            Learning</span>
                    </div>
                </div>
            </div>

            <!-- Project 6 with Auto-Sliding Gallery -->
            <div
                class="bg-gradient-to-br from-indigo-900 to-purple-900 rounded-xl shadow-2xl overflow-hidden text-white mt-8 fade-in-up stagger-2">
                <div class="p-8 md:p-12">
                    <h4 class="text-4xl font-bold mb-4 slide-in-right">Color-Based Image Retrieval System (CBIR)</h4>
                    <div id=""
                        style="overflow-y:scroll; height:200px; scrollbar-color: #000077bd #bbda5500;">
                        <p class="text-gray-300 mb-6 text-lg slide-in-left stagger-1">Engineered an intelligent
                            content-based image retrieval system that enables efficient searching and matching of images
                            within large databases based on color composition and distribution patterns. Implemented
                            sophisticated color histogram analysis algorithms that extract and quantify color
                            information
                            across multiple color spaces including RGB, HSV, and LAB, providing comprehensive color
                            characterization robust to lighting variations. Developed advanced similarity matching
                            algorithms incorporating multiple distance metrics such as Euclidean distance, Manhattan
                            distance, and chi-square distance to compute similarity scores between query images and
                            database
                            entries. The system employs intelligent indexing structures to dramatically accelerate
                            search
                            operations, enabling near-instantaneous results even in databases containing hundreds of
                            thousands of images. Created a feature extraction pipeline that segments images into
                            regions,
                            analyzes color distribution within each region, and constructs compact color descriptors
                            that
                            capture essential visual information while minimizing storage requirements. Implemented
                            query
                            refinement mechanisms allowing users to iteratively improve search results through relevance
                            feedback, where the system learns from user selections to adjust similarity metrics and
                            deliver
                            increasingly accurate results. Designed an intuitive user interface featuring drag-and-drop
                            image upload, visual similarity sliders for adjusting search parameters, grid-based results
                            display with relevance scores, and filtering options based on color dominance, saturation
                            levels, and brightness ranges. Integrated batch processing capabilities for analyzing and
                            indexing large image collections efficiently. The system includes performance optimization
                            techniques such as parallel processing for feature extraction and caching mechanisms for
                            frequently accessed results. Practical applications include digital asset management for
                            creative agencies, e-commerce product matching, scientific image analysis in fields like
                            medical
                            imaging and satellite imagery, and forensic investigation tools for finding visually similar
                            images across large datasets.</p>
                    </div>

                    <!-- Auto-Sliding Image Carousel -->
                    <div class="carousel-container mt-8 mb-6 rounded-lg">
                        <div class="carousel-track" style="animation-duration: 25s;">
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop"
                                        alt="CBIR Interface" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&h=300&fit=crop"
                                        alt="Color Analysis" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400&h=300&fit=crop"
                                        alt="Search Results" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <!-- Duplicate for seamless loop -->
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop"
                                        alt="CBIR Interface" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&h=300&fit=crop"
                                        alt="Color Analysis" class="w-full h-48 object-cover">
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="img-zoom rounded-lg overflow-hidden">
                                    <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400&h=300&fit=crop"
                                        alt="Search Results" class="w-full h-48 object-cover">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-6">
                        <span
                            class="bg-pink-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-2">Python</span>
                        <span
                            class="bg-indigo-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-3">OpenCV</span>
                        <span
                            class="bg-purple-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-4">Image
                            Processing</span>
                        <span
                            class="bg-cyan-500 text-white px-4 py-2 rounded-full text-sm font-semibold tag-float bounce-in stagger-5">Algorithm
                            Design</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div class="gradient-bg text-white py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl float-animation"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-300 rounded-full blur-3xl float-animation"
                style="animation-delay: 1.5s;"></div>
        </div>
        <div class="container mx-auto px-6 text-center relative z-10">
            <h2 class="text-4xl font-bold mb-4 bounce-in">Let's Work Together</h2>
            <p class="text-xl mb-8 fade-in-up stagger-1">Interested in collaborating? Get in touch!</p>
            <div class="flex justify-center gap-6 flex-wrap">
                <a href="mailto:luaihawi@gmail.com"
                    class="bg-white text-purple-600 px-8 py-3 rounded-full font-semibold hover:scale-110 transition-transform bounce-in stagger-2">Email
                    Me</a>
                <a href="https://github.com/luai-hawi"
                    class="bg-gray-800 text-white px-8 py-3 rounded-full font-semibold hover:scale-110 transition-transform bounce-in stagger-3">GitHub</a>
                <a href="https://www.linkedin.com/in/luai-hawi-377958289"
                    class="bg-blue-600 text-white px-8 py-3 rounded-full font-semibold hover:scale-110 transition-transform bounce-in stagger-4">LinkedIn</a>
            </div>
        </div>
    </div>

    <script>
        // Trigger animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in-up, .slide-in-left, .slide-in-right, .bounce-in, .rotate-in').forEach(el => {
            observer.observe(el);
        });

        // Smooth scroll for View Projects button
        document.querySelector('button.pulse-glow').addEventListener('click', () => {
            document.querySelector('.container').scrollIntoView({
                behavior: 'smooth'
            });
        });
    </script>

</body>

</html>
