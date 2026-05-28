<?php
    $vista_actual = isset($_GET["mod"]) ? $_GET["mod"] : "home";
    $titulo_seo = "Macrom Autopartes | Refacciones y Accesorios Automotrices";
    $desc_seo = "¡Compra en línea! La mejor calidad en refacciones y auto partes a precios competitivos. Nos especializamos en Nissan, Volkswagen y Chevrolet. Envíos a todo México.";
    
    switch ($vista_actual) {
        case 'catalogo':
            $titulo_seo = "Catálogo de Refacciones | Macrom Autopartes";
            $desc_seo = "Explora nuestro catálogo completo de refacciones. Filtra por marca, vehículo y año para encontrar la pieza exacta que necesitas.";
            break;
        case 'login':
            $titulo_seo = "Iniciar Sesión | Macrom Autopartes";
            break;
        case 'register':
            $titulo_seo = "Crear Cuenta | Macrom Autopartes";
            break;
        case 'Compras':
            $titulo_seo = "Carrito de Compras | Macrom Autopartes";
            break;
        case 'Profile':
            $titulo_seo = "Mi Perfil | Macrom Autopartes";
            break;
    }
?>
<!DOCTYPE html>
<html lang="es" class="Macrom_page">
    <head>
        <base href="/">
	    <title><?php echo $titulo_seo; ?></title>
	    <meta charset="UTF-8">
	    <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="language" content="es-MX" />
        <meta name="country" content="MEX" />
        <meta name="currency" content="$" />
        <meta name="description" content="<?php echo $desc_seo; ?>" />
        <meta name="Abstract" content="Refaccionaría en linea, Autopartes y Accesorios | Macrom" />                
        <meta name="keywords" content="Refacciones en línea, Colima, Macrom, Jalisco, Refaccionaria, Auto Partes, Nissan, Chevrolet, Volkswagen, Vocho, VW, Tsuru, Chevy, autopartes México"/>
        
        <meta name="robots" content="index, follow" />
        <meta name="google-site-verification" content="qCM6XimrT8ue8qAcveloUw">
        
        <meta property="og:type" content="business.business">
        <meta property="og:title" content="<?php echo $titulo_seo; ?>">
        <meta property="og:description" content="<?php echo $desc_seo; ?>">
        <meta property="og:image" itemprop="image" content="https://macromautopartes.com/images/icons/previwmacrom.png">
        <meta property="og:url" content="https://macromautopartes.com/">
        <meta property="og:site_name" content="Macrom Autopartes">
        <meta property="og:locale" content="es_MX">
        
        <meta property="business:contact_data:street_address" content="Av. Benito Juárez #164 Col. La Gloria">
        <meta property="business:contact_data:locality" content="Villa de Álvarez">
        <meta property="business:contact_data:region" content="Colima">
        <meta property="business:contact_data:postal_code" content="28980">
        <meta property="business:contact_data:country_name" content="Mexico">
        <meta name="theme-color" content="#ffffff">
        <?php
            $url_limpia = "https://macromautopartes.com" . strtok($_SERVER["REQUEST_URI"], '?');
        ?>
        <link rel="canonical" href="<?php echo $url_limpia; ?>" />
        <script type="application/ld+json">
            {
            "@context": "https://schema.org",
            "@type": "AutoPartsStore",
            "name": "Macrom Autopartes",
            "logo": "https://macromautopartes.com/images/icons/logo%20original.svg",
            "image": "https://macromautopartes.com/images/icons/previwmacrom.png",
            "@id": "https://macromautopartes.com",
            "url": "https://macromautopartes.com",
            "telephone": "+523122682028",
            "priceRange": "$$",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "Av. Benito Juárez #164 Col. La Gloria",
                "addressLocality": "Villa de Álvarez",
                "addressRegion": "Colima",
                "postalCode": "28980",
                "addressCountry": "MX"
            },
            "sameAs": [
                "https://www.facebook.com/MacromAutopartes/",
                "https://www.instagram.com/macromautopartes/",
                "https://www.tiktok.com/@macromautopartes?lang=en",
                "https://www.youtube.com/channel/UCbVJJH6AIZJ9lH7fE_2rtwA"
            ]
            }
        </script>

        <style>
            [ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
                display: none !important;
            }
        </style>

        <link rel="icon" type="image/png" href="/images/icons/FaviconM.png"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    	<link rel="stylesheet" type="text/css" href="/fonts/fontawesome-5/css/all.min.css">
    	<link rel="stylesheet" type="text/css" href="/fonts/elegant-font/html-css/style.css">
    	<link rel="stylesheet" type="text/css" href="/vendor/animate/animate.css">
    	<link rel="stylesheet" type="text/css" href="/vendor/daterangepicker/daterangepicker.css">
    	<link rel="stylesheet" type="text/css" href="/vendor/lightbox2/css/lightbox.min.css">
        <link rel="stylesheet" type="text/css" href="/vendor/toastr/build/toastr.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.7/css/jquery.dataTables.css">
    	<link rel="stylesheet" type="text/css" href="/css/util.css">
        <link rel="stylesheet" type="text/css" href="/css/main.css">
        <link rel="preload" href="/css/main.css" as="style">
        <link rel="stylesheet" type="text/css" href="/css/otra.css">
        <link rel="stylesheet" href="/css/normalize.css">

        <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0GT52FN43"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-T0GT52FN43');
        </script>
    </head>
    <body class="body__theme--light" ng-app="tsuruVolks" ng-cloak id="BodyDark">
        
        <div id="preloader">
            <div id="status">&nbsp;</div>
        </div>

        <?php 
            include("./includes/cabecera.php");
            
            if (file_exists($path_modulo)){
                include($path_modulo);
            } else {
                die ('Error al cargar el modulo <b>'.$modulo.'</b>. No existe el archivo <b>'.$conf[$modulo]['archivo'].'</b>');
            }

            include("./includes/footer.php");
        ?>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <script type="text/javascript" src="/vendor/momentjs/moment.min.js"></script>
        <script src="https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js"></script>
        <script src="/tv-admin/asset/Plugins/numeric/jquery.numeric.js"></script>
        <script type="text/javascript" src="/tv-admin/asset/Js/angular/angular.min.js"></script>
        <script src="/tv-admin/asset/Js/angular/angular-datatables.min.js"></script>
        <script src="/tv-admin/asset/Js/angular/angular-recaptcha.min.js"></script>
        <script type="text/javascript" src="/tv-admin/asset/Js/angular/first.js"></script>
        <script type="text/javascript" src="/js/Cabecera.js"></script>
        <script type="text/javascript">$_SESSION = <?php print json_encode($_SESSION)?>;</script>
        <script src="https://unpkg.com/@popperjs/core@2"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js"></script>
        <script type="text/javascript" src="/vendor/JsZip/dist/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script type="text/javascript" src="/vendor/toastr/build/toastr.min.js"></script>
	    <script src="/js/main.js"></script>

        <?php
            $mod = isset($_GET["mod"]) ? $_GET["mod"] : "home";
            switch ($mod) {
                case 'home':
                    echo "<script type='text/javascript' src='/modulo/home/Js/$mod.js'></script>";
                    break;
                case 'catalogo':
                    echo "<script type='text/javascript' src='/vendor/noui/nouislider.min.js'></script>";
                    echo "<script type='text/javascript' src='/modulo/Catalogo/Js/$mod.js'></script>";
                    break;
                case 'login':
                case 'register':
                    echo "<script type='text/javascript' src='/modulo/Login/Js/Login.js'></script>";
                    break;
                case 'ProcesoCompra':
                case 'Compras':
                case 'Blog':
                case 'Profile':
                    echo "<script type='text/javascript' src='/modulo/$mod/Js/$mod.js'></script>";
                    break;
            }
        ?>
    </body>
</html>