document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    // =======================================================
    // 1. UI General (Scroll, Preloader, Hero)
    // =======================================================
    const btnTop = document.getElementById("myBtn");
    const windowH = window.innerHeight / 2;

    window.addEventListener('scroll', () => {
        if (btnTop) {
            btnTop.style.display = window.scrollY > windowH ? 'flex' : 'none';
        }
    });

    if (btnTop) {
        btnTop.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    window.addEventListener('load', () => {
        const preloader = document.getElementById('preloader');
        
        if (preloader) {
            preloader.classList.add('preloader-fade-out');
            setTimeout(() => { 
                preloader.style.display = 'none'; 
            }, 400);
        }
    });

    // Galería Hero
    const hero = document.querySelector('.hero');
    const activateGallery = (e) => {
        if (!e.target.matches('.secundaria') || !hero) return;
        [hero.src, e.target.src] = [e.target.src, hero.src];
    };

    if (window.innerWidth > 800) {
        window.addEventListener('mouseover', activateGallery);
    } else {
        window.addEventListener('click', activateGallery);
        const btnSearch = document.getElementById('btn-search');
        if(btnSearch) btnSearch.classList.remove('js-show-header-dropdown');
    }

    // =======================================================
    // 2. Dark Mode (Lógica Centralizada)
    // =======================================================
    const darkmodeSwitches = document.querySelectorAll(".switch__darkmode");
    const currentTheme = localStorage.getItem('darkmode');

    const setTheme = (theme) => {
        if (theme === 'dark') {
            document.body.classList.add('dark-theme');
            darkmodeSwitches.forEach(sw => sw.classList.replace('fa-sun', 'fa-moon'));
            darkmodeSwitches.forEach(sw => sw.checked = true);
        } else {
            document.body.classList.remove('dark-theme');
            darkmodeSwitches.forEach(sw => sw.classList.replace('fa-moon', 'fa-sun'));
            darkmodeSwitches.forEach(sw => sw.checked = false);
        }
    };

    if (currentTheme) setTheme(currentTheme);

    darkmodeSwitches.forEach(sw => {
        sw.addEventListener('click', (e) => {
            const newTheme = e.target.checked ? "dark" : "light";
            localStorage.setItem('darkmode', newTheme);
            setTheme(newTheme);
        });
    });

    // =======================================================
    // 3. Detección de Móvil
    // =======================================================
    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    const bodyDark = document.getElementById("BodyDark"); 
    
    if (bodyDark) {
        bodyDark.classList.add(isMobile ? "mobiledevice" : "desktop");
    }

    const urlParams = new URLSearchParams(window.location.search);
    const moduloActual = urlParams.get('mod');

    if (moduloActual === 'catalogo') {
        if (isMobile) {
            document.querySelectorAll(".filtros__subtitle").forEach(el => {
                el.addEventListener("click", e => {
                    const objetivo = e.target.className.replace("filtros__subtitle name__", "").trim();
                    const desplegar = document.querySelector(".opciones__" + objetivo);
                    if(desplegar) desplegar.classList.toggle("dis-none");
                });
            });
        } else {
            document.querySelectorAll(".contenido__opciones, .filtros").forEach(el => {
                el.classList.remove("dis-none");
            });
        }
    }

    // =======================================================
    // 4. Modales (Delegación de Eventos y Clics Fuera)
    // =======================================================
    const domPage = document.querySelector(".Macrom_page");
    const contHeader = document.querySelector(".contenedor__header");
    const sidebarMenu = document.querySelector("#abrirModal6");

    const toggleModalOverflow = (isOpening) => {
        if (bodyDark) bodyDark.classList.toggle("no-overflow", isOpening);
        if (domPage) domPage.classList.toggle("no-overflow", isOpening);
        if (contHeader) isOpening ? contHeader.classList.remove("pd-1") : contHeader.classList.add("pd-1");
    };

    document.addEventListener("click", (e) => {
        if (e.target.matches('.click')) {
            const targetId = e.target.dataset.target || `ventanaModal${e.target.id.split('l')[1]}`;
            const modal = document.getElementById(targetId);
            if (modal) {
                if (modal.classList.contains('sidebar__mobil')) {
                    modal.classList.add('sidebar-active');
                } else {
                    modal.style.display = "block";
                }
                toggleModalOverflow(true);
            }
        }

        if (e.target.matches('.closem') || e.target.matches('[class^="cerrar"]')) {
            const modal = e.target.closest('[id^="ventanaModal"]') || e.target.closest('.modal') || e.target.closest('.sidebar__mobil');
            if (modal) {
                if (modal.classList.contains('sidebar__mobil')) {
                    modal.classList.remove('sidebar-active');
                } else {
                    modal.style.display = 'none';
                }
                toggleModalOverflow(false);
                if (sidebarMenu) {
                    sidebarMenu.classList.add("fa-bars");
                    sidebarMenu.classList.remove("fa-reply");
                }
            }
        }

        if ((e.target.id && e.target.id.startsWith('ventanaModal')) || e.target.classList.contains('sidebar__mobil')) {
            if (e.target.classList.contains('sidebar__mobil')) {
                e.target.classList.remove('sidebar-active');
            } else {
                e.target.style.display = 'none';
            }
            toggleModalOverflow(false);
            if (sidebarMenu) {
                sidebarMenu.classList.add("fa-bars");
                sidebarMenu.classList.remove("fa-reply");
            }
        }
    });

    // =======================================================
    // 5. Enrutamiento de Menú Activo
    // =======================================================
    const path = window.location.pathname.toLowerCase();
    const rutas = [
        { ruta: '/catalogo',          selector: '#sidebar1' },
        { ruta: '/compras',           selector: '#sidebar2' },
        { ruta: '/procesocompra',     selector: '#sidebar2' },
        { ruta: '/nosotros',          selector: '#sidebar4' },
        { ruta: '/profile/session',   selector: '#sidebar5' },
        { ruta: '/profile/mispedidos',selector: '#sidebar6' },
        { ruta: '/profile/direcciones',selector: '#sidebar7' },
        { ruta: '/profile/facturacion',selector: '#sidebar8' },
        { ruta: '/blog',              selector: '#sidebar9' },
        { ruta: '/contacto',          selector: '#sidebar10' }
    ];

    const coincidencia = rutas.find(r => path.includes(r.ruta));
    const activeSidebarSelector = coincidencia ? coincidencia.selector : '#sidebar0';

    document.querySelectorAll('.sidebar__click').forEach(el => el.classList.remove('sidebar__active'));

    const activeSidebar = document.querySelector(activeSidebarSelector);
    if (activeSidebar) activeSidebar.classList.add('sidebar__active');

    // =======================================================
    // 6. Menús Desplegables de Cabecera (Usuario y Carrito)
    // =======================================================
    const dropdownTriggers = document.querySelectorAll('.js-show-header-dropdown');
    const usercba = document.getElementById('usercba');
    const divcarri = document.getElementById('divcarri');
    const carrisvg = document.getElementById('carrisvg');
    
    const resetHeaderStyles = () => {
        if (usercba) usercba.style.backgroundColor = "transparent";
        if (carrisvg) carrisvg.style.filter = "brightness(0) invert(1)";
        if (divcarri) divcarri.style.backgroundColor = "transparent";
        
        dropdownTriggers.forEach(el => el.style.color = "#fff");
        const btnSearch = document.getElementById('btn-search');
        if (btnSearch) btnSearch.style.color = "#000";
    };

    dropdownTriggers.forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.stopPropagation(); 
            
            const parent = trigger.parentElement;
            const dropdown = parent.querySelector('.header-dropdown');
            const isAlreadyOpen = dropdown && dropdown.classList.contains('show-header-dropdown');
            const isDarkMode = localStorage.getItem('darkmode') === "dark";
            
            document.querySelectorAll('.header-dropdown').forEach(menu => {
                menu.classList.remove('show-header-dropdown');
            });
            resetHeaderStyles();

            if (!isAlreadyOpen && dropdown) {
                dropdown.classList.add('show-header-dropdown');
                
                dropdownTriggers.forEach(el => el.style.color = "#000");
                const btnSearch = document.getElementById('btn-search');
                if (btnSearch) btnSearch.style.color = "#000";

                if (parent.matches(".header-wrapicon1")) { 
                    if (carrisvg) carrisvg.style.filter = "brightness(0) invert(1)";
                    if (divcarri) divcarri.style.backgroundColor = "transparent";
                    if (usercba) usercba.style.backgroundColor = isDarkMode ? "#7f7f7f" : "#fff";
                } else if (parent.matches(".header-wrapicon2")) { 
                    if (usercba) usercba.style.backgroundColor = "transparent";
                    if (divcarri) divcarri.style.backgroundColor = isDarkMode ? "#7f7f7f" : "white";
                    if (carrisvg && !isDarkMode) carrisvg.style.filter = "brightness(2)";
                }
            }
        });
    });

    document.querySelectorAll('.header-dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    });

    window.addEventListener('click', () => {
        document.querySelectorAll('.header-dropdown').forEach(menu => {
            menu.classList.remove('show-header-dropdown');
        });
        resetHeaderStyles();
    });

});