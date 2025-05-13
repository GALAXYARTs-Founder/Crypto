/**
 * Стена логотипов с использованием Three.js
 * CryptoLogoWall
 */

// Переменные
let scene, camera, renderer, raycaster, mouse;
let logos = [];
let tooltipElement;
let selectedLogo = null;
let isAnimating = true;
let windowWidth = window.innerWidth;
let windowHeight = window.innerHeight;

// Настройки
const config = {
    spacing: 300,            // Расстояние между логотипами
    rows: 5,                 // Количество рядов
    columns: 7,              // Количество колонок
    rotationSpeed: 0.001,    // Скорость вращения
    floatAmplitude: 0.2,     // Амплитуда плавания
    floatSpeed: 0.003        // Скорость плавания
};

// Инициализация
function init() {
    // Создаем сцену
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x121212);
    
    // Создаем камеру
    const aspectRatio = windowWidth / windowHeight;
    camera = new THREE.PerspectiveCamera(60, aspectRatio, 0.1, 10000);
    camera.position.z = 1500;
    
    // Добавляем свет
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);
    
    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(1, 1, 1);
    scene.add(directionalLight);
    
    // Создаем рендерер
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(windowWidth, windowHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    document.getElementById('logo-wall').appendChild(renderer.domElement);
    
    // Инициализируем инструменты для взаимодействия
    raycaster = new THREE.Raycaster();
    mouse = new THREE.Vector2();
    
    // Создаем элемент для подсказок
    createTooltip();
    
    // Загружаем логотипы
    loadLogos();
    
    // Добавляем обработчики событий
    addEventListeners();
    
    // Запускаем анимацию
    animate();
}

// Создаем элемент подсказки
function createTooltip() {
    tooltipElement = document.createElement('div');
    tooltipElement.className = 'logo-tooltip';
    tooltipElement.style.display = 'none';
    tooltipElement.style.position = 'absolute';
    tooltipElement.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
    tooltipElement.style.color = 'white';
    tooltipElement.style.padding = '10px';
    tooltipElement.style.borderRadius = '5px';
    tooltipElement.style.fontSize = '14px';
    tooltipElement.style.zIndex = '1000';
    tooltipElement.style.pointerEvents = 'none';
    tooltipElement.style.backdropFilter = 'blur(5px)';
    tooltipElement.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.2)';
    document.body.appendChild(tooltipElement);
}

// Загрузка логотипов
function loadLogos() {
    // Получаем данные логотипов с сервера
    fetch('api/logos.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logos.length > 0) {
                createLogoGrid(data.logos);
            } else {
                // Если логотипов нет или ошибка загрузки, создаем заглушку
                createEmptyState();
            }
        })
        .catch(error => {
            console.error('Error loading logos:', error);
            createEmptyState();
        });
}

// Создание сетки логотипов
function createLogoGrid(logosData) {
    // Текстурный загрузчик
    const textureLoader = new THREE.TextureLoader();
    
    // Определяем центр сетки
    const totalWidth = config.columns * config.spacing;
    const totalHeight = config.rows * config.spacing;
    const startX = -totalWidth / 2 + config.spacing / 2;
    const startY = totalHeight / 2 - config.spacing / 2;
    
    // Создаем группу для логотипов
    const logosGroup = new THREE.Group();
    scene.add(logosGroup);
    
    // Подготавливаем материал для заглушки
    const placeholderGeometry = new THREE.PlaneGeometry(200, 200);
    const placeholderMaterial = new THREE.MeshBasicMaterial({ 
        transparent: true, 
        opacity: 0.5,
        color: 0x666666
    });
    
    // Определяем максимальное количество логотипов для отображения
    const maxLogos = config.rows * config.columns;
    const logosToShow = logosData.slice(0, maxLogos);
    
    // Если логотипов меньше чем ячеек, добавляем заглушки
    const logoPlaceholders = Array(maxLogos).fill(null);
    logosToShow.forEach((logo, index) => {
        logoPlaceholders[index] = logo;
    });
    
    // Создаем сетку
    for (let row = 0; row < config.rows; row++) {
        for (let col = 0; col < config.columns; col++) {
            const index = row * config.columns + col;
            const logoData = logoPlaceholders[index];
            
            let logoMesh;
            
            if (logoData) {
                // Создаем логотип
                textureLoader.load(logoData.logo_path, (texture) => {
                    const material = new THREE.MeshBasicMaterial({ 
                        map: texture, 
                        transparent: true 
                    });
                    const geometry = new THREE.PlaneGeometry(200, 200);
                    logoMesh = new THREE.Mesh(geometry, material);
                    
                    // Позиционируем логотип
                    logoMesh.position.x = startX + col * config.spacing;
                    logoMesh.position.y = startY - row * config.spacing;
                    logoMesh.position.z = 0;
                    
                    // Добавляем случайное смещение для лучшего 3D эффекта
                    logoMesh.position.z += Math.random() * 100 - 50;
                    
                    // Добавляем данные к мешу для взаимодействия
                    logoMesh.userData = {
                        id: logoData.id,
                        name: logoData.name,
                        website: logoData.website,
                        rating: logoData.average_rating || 0,
                        reviewCount: logoData.review_count || 0,
                        originalPosition: logoMesh.position.clone(),
                        originalRotation: logoMesh.rotation.clone(),
                        floatOffset: Math.random() * Math.PI * 2 // Случайное смещение для эффекта плавания
                    };
                    
                    // Добавляем логотип в сцену и в массив для взаимодействия
                    logosGroup.add(logoMesh);
                    logos.push(logoMesh);
                });
            } else {
                // Создаем заглушку для места
                logoMesh = new THREE.Mesh(placeholderGeometry, placeholderMaterial);
                
                // Позиционируем заглушку
                logoMesh.position.x = startX + col * config.spacing;
                logoMesh.position.y = startY - row * config.spacing;
                logoMesh.position.z = -100; // Заглушки немного дальше от камеры
                
                // Добавляем данные к мешу
                logoMesh.userData = {
                    isPlaceholder: true,
                    originalPosition: logoMesh.position.clone(),
                    originalRotation: logoMesh.rotation.clone(),
                    floatOffset: Math.random() * Math.PI * 2
                };
                
                // Добавляем заглушку в сцену
                logosGroup.add(logoMesh);
                logos.push(logoMesh);
            }
        }
    }
}

// Создание пустого состояния (если нет логотипов)
function createEmptyState() {
    // Создаем текст "Добавьте первый логотип!"
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    canvas.width = 512;
    canvas.height = 256;
    context.fillStyle = '#ffffff';
    context.font = 'bold 32px Arial';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText('Add first logo!', canvas.width / 2, canvas.height / 2);
    
    // Создаем текстуру из canvas
    const texture = new THREE.CanvasTexture(canvas);
    const material = new THREE.MeshBasicMaterial({ 
        map: texture, 
        transparent: true 
    });
    const geometry = new THREE.PlaneGeometry(512, 256);
    const textMesh = new THREE.Mesh(geometry, material);
    
    // Размещаем текст в центре
    textMesh.position.set(0, 0, 0);
    
    // Добавляем анимацию парения
    textMesh.userData = {
        isEmptyState: true,
        floatOffset: 0
    };
    
    // Добавляем в сцену
    scene.add(textMesh);
    logos.push(textMesh);
}

// Добавление обработчиков событий
function addEventListeners() {
    // Клик по логотипу
    document.addEventListener('mousedown', onMouseDown, false);
    
    // Движение мыши для подсказки
    document.addEventListener('mousemove', onMouseMove, false);
    
    // Изменение размера окна
    window.addEventListener('resize', onWindowResize, false);
    
    // Кнопка для добавления логотипа
    const addLogoButton = document.getElementById('add-logo-button');
    if (addLogoButton) {
        addLogoButton.addEventListener('click', () => {
            window.location.href = 'add-logo.php';
        });
    }
}

// Обработчик движения мыши
function onMouseMove(event) {
    // Обновляем координаты мыши для raycaster
    mouse.x = (event.clientX / windowWidth) * 2 - 1;
    mouse.y = -(event.clientY / windowHeight) * 2 + 1;
    
    // Проверяем наведение на логотипы
    raycaster.setFromCamera(mouse, camera);
    const intersects = raycaster.intersectObjects(logos);
    
    // Если есть пересечение
    if (intersects.length > 0) {
        const intersectedObject = intersects[0].object;
        
        // Проверяем, что это логотип, а не заглушка
        if (!intersectedObject.userData.isPlaceholder && !intersectedObject.userData.isEmptyState) {
            // Меняем курсор
            document.body.style.cursor = 'pointer';
            
            // Показываем подсказку
            showTooltip(intersectedObject, event.clientX, event.clientY);
            
            // Слегка увеличиваем логотип при наведении
            if (selectedLogo !== intersectedObject) {
                gsap.to(intersectedObject.scale, {
                    x: 1.1,
                    y: 1.1,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            }
        } else {
            // Возвращаем стандартный курсор
            document.body.style.cursor = 'default';
            
            // Скрываем подсказку
            hideTooltip();
        }
    } else {
        // Возвращаем стандартный курсор
        document.body.style.cursor = 'default';
        
        // Скрываем подсказку
        hideTooltip();
        
        // Возвращаем размер всех логотипов, которые не выбраны
        logos.forEach(logo => {
            if (logo !== selectedLogo && logo.scale.x > 1) {
                gsap.to(logo.scale, {
                    x: 1,
                    y: 1,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            }
        });
    }
}

// Обработчик клика мыши
function onMouseDown(event) {
    // Проверяем клик по логотипам
    raycaster.setFromCamera(mouse, camera);
    const intersects = raycaster.intersectObjects(logos);
    
    if (intersects.length > 0) {
        const intersectedObject = intersects[0].object;
        
        // Проверяем, что это логотип, а не заглушка
        if (!intersectedObject.userData.isPlaceholder && !intersectedObject.userData.isEmptyState) {
            // Если это заглушка для добавления нового логотипа
            if (intersectedObject.userData.isAddButton) {
                window.location.href = 'add-logo.php';
                return;
            }
            
            // Проверяем, выбран ли уже этот логотип
            if (selectedLogo === intersectedObject) {
                // Если выбран, перенаправляем на страницу с деталями
                window.location.href = 'view.php?id=' + intersectedObject.userData.id;
            } else {
                // Выбираем логотип
                selectLogo(intersectedObject);
            }
        } else if (intersectedObject.userData.isEmptyState) {
            // Переход на страницу добавления логотипа, если кликнули на пустое состояние
            window.location.href = 'add-logo.php';
        }
    } else if (selectedLogo) {
        // Снимаем выбор, если кликнули вне логотипа
        deselectLogo();
    }
}

// Выбор логотипа
function selectLogo(logo) {
    // Если уже выбран другой логотип, снимаем выбор
    if (selectedLogo && selectedLogo !== logo) {
        deselectLogo();
    }
    
    // Устанавливаем выбранный логотип
    selectedLogo = logo;
    
    // Анимируем перемещение логотипа вперед
    gsap.to(logo.position, {
        z: 200,
        duration: 0.5,
        ease: 'power2.out'
    });
    
    // Анимируем увеличение логотипа
    gsap.to(logo.scale, {
        x: 1.5,
        y: 1.5,
        duration: 0.5,
        ease: 'power2.out'
    });
    
    // Показываем информацию о логотипе
    showLogoDetails(logo);
}

// Снятие выбора с логотипа
function deselectLogo() {
    if (!selectedLogo) return;
    
    // Анимируем возвращение логотипа на место
    gsap.to(selectedLogo.position, {
        x: selectedLogo.userData.originalPosition.x,
        y: selectedLogo.userData.originalPosition.y,
        z: selectedLogo.userData.originalPosition.z,
        duration: 0.5,
        ease: 'power2.out'
    });
    
    // Анимируем уменьшение логотипа
    gsap.to(selectedLogo.scale, {
        x: 1,
        y: 1,
        duration: 0.5,
        ease: 'power2.out'
    });
    
    // Скрываем информацию о логотипе
    hideLogoDetails();
    
    // Снимаем выбор
    selectedLogo = null;
}

// Показать подсказку
function showTooltip(logo, x, y) {
    // Проверяем, что это обычный логотип
    if (logo.userData.isPlaceholder || !logo.userData.name) {
        return;
    }
    
    // Формируем содержимое подсказки
    const rating = logo.userData.rating ? `⭐ ${logo.userData.rating.toFixed(1)}` : '';
    const reviews = logo.userData.reviewCount ? `(${logo.userData.reviewCount} reviews)` : '';
    
    tooltipElement.innerHTML = `
        <strong>${logo.userData.name}</strong>
        <div>${rating} ${reviews}</div>
        <div style="font-size: 12px; opacity: 0.7;">Click for details</div>
    `;
    
    // Позиционируем и показываем подсказку
    tooltipElement.style.left = (x + 15) + 'px';
    tooltipElement.style.top = (y - 15) + 'px';
    tooltipElement.style.display = 'block';
}

// Скрыть подсказку
function hideTooltip() {
    tooltipElement.style.display = 'none';
}

// Показать детали логотипа
function showLogoDetails(logo) {
    // Находим или создаем элемент для деталей
    let detailsElement = document.getElementById('logo-details');
    
    if (!detailsElement) {
        detailsElement = document.createElement('div');
        detailsElement.id = 'logo-details';
        detailsElement.className = 'logo-details-panel';
        document.body.appendChild(detailsElement);
    }
    
    // Формируем звездный рейтинг
    const rating = logo.userData.rating || 0;
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    
    let starsHTML = '';
    for (let i = 0; i < 5; i++) {
        if (i < fullStars) {
            starsHTML += '<span class="star full">★</span>';
        } else if (i === fullStars && hasHalfStar) {
            starsHTML += '<span class="star half">★</span>';
        } else {
            starsHTML += '<span class="star empty">★</span>';
        }
    }
    
    // Заполняем детали
    detailsElement.innerHTML = `
        <div class="details-header">
            <h2>${logo.userData.name}</h2>
            <span class="close-button">&times;</span>
        </div>
        <div class="details-content">
            <div class="rating-container">
                <div class="stars">${starsHTML}</div>
                <div class="rating-text">${rating.toFixed(1)} from ${logo.userData.reviewCount || 0} reviews</div>
            </div>
            ${logo.userData.website ? `<a href="${logo.userData.website}" target="_blank" class="website-link">Visit Website</a>` : ''}
            <div class="action-buttons">
                <a href="view.php?id=${logo.userData.id}" class="view-button">View Details</a>
                <a href="add-review.php?id=${logo.userData.id}" class="review-button">Add Review $1</a>
            </div>
        </div>
    `;
    
    // Добавляем обработчик для закрытия
    const closeButton = detailsElement.querySelector('.close-button');
    closeButton.addEventListener('click', deselectLogo);
    
    // Показываем панель
    detailsElement.style.display = 'block';
    
    // Анимируем появление
    gsap.fromTo(detailsElement, {
        opacity: 0,
        y: 20
    }, {
        opacity: 1,
        y: 0,
        duration: 0.3,
        ease: 'power2.out'
    });
}

// Скрыть детали логотипа
function hideLogoDetails() {
    const detailsElement = document.getElementById('logo-details');
    
    if (detailsElement) {
        // Анимируем исчезновение
        gsap.to(detailsElement, {
            opacity: 0,
            y: 20,
            duration: 0.3,
            ease: 'power2.in',
            onComplete: () => {
                detailsElement.style.display = 'none';
            }
        });
    }
}

// Обработчик изменения размера окна
function onWindowResize() {
    windowWidth = window.innerWidth;
    windowHeight = window.innerHeight;
    
    // Обновляем камеру
    camera.aspect = windowWidth / windowHeight;
    camera.updateProjectionMatrix();
    
    // Обновляем рендерер
    renderer.setSize(windowWidth, windowHeight);
}

// Функция анимации (вызывается каждый кадр)
function animate() {
    requestAnimationFrame(animate);
    
    // Анимируем только если включена анимация
    if (isAnimating) {
        // Анимация парения логотипов
        const time = Date.now() * 0.001; // Текущее время в секундах
        
        logos.forEach(logo => {
            if (!logo.userData) return;
            
            // Плавающий эффект для всех логотипов (исключая выбранный)
            if (logo !== selectedLogo) {
                const floatOffset = logo.userData.floatOffset || 0;
                
                // Парение вверх-вниз
                logo.position.y = 
                    (logo.userData.originalPosition ? logo.userData.originalPosition.y : 0) + 
                    Math.sin(time * config.floatSpeed + floatOffset) * config.floatAmplitude * 10;
                
                // Легкое вращение
                logo.rotation.x = Math.sin(time * config.floatSpeed * 0.5 + floatOffset) * 0.05;
                logo.rotation.y = Math.cos(time * config.floatSpeed * 0.7 + floatOffset) * 0.05;
            }
        });
        
        // Медленное вращение камеры вокруг сцены
        camera.position.x = Math.sin(time * 0.1) * 100;
        camera.position.y = Math.cos(time * 0.1) * 50;
        camera.lookAt(scene.position);
    }
    
    // Рендеринг сцены
    renderer.render(scene, camera);
}

// Запускаем инициализацию при загрузке страницы
document.addEventListener('DOMContentLoaded', init);

// Экспортируем функции для внешнего использования
window.logoWall = {
    toggleAnimation: function(animate) {
        isAnimating = animate;
    },
    refresh: function() {
        // Очищаем текущие логотипы
        logos.forEach(logo => {
            scene.remove(logo);
        });
        logos = [];
        
        // Загружаем логотипы заново
        loadLogos();
    }
};