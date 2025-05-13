/**
 * Основной JavaScript файл
 * CryptoLogoWall
 */

// Ждем загрузку DOM перед выполнением скриптов
document.addEventListener('DOMContentLoaded', function() {
  // Инициализация общих компонентов
  initComponents();
  
  // Обработка форм с валидацией
  setupFormValidation();
  
  // Анимации элементов при прокрутке
  setupScrollAnimations();
});

/**
* Инициализация общих компонентов
*/
function initComponents() {
  // Инициализация выпадающих меню
  const dropdowns = document.querySelectorAll('.dropdown-toggle');
  
  dropdowns.forEach(dropdown => {
      dropdown.addEventListener('click', function(e) {
          e.preventDefault();
          const dropdownContent = this.nextElementSibling;
          
          // Закрываем все другие открытые дропдауны
          document.querySelectorAll('.dropdown-content.active').forEach(item => {
              if (item !== dropdownContent) {
                  item.classList.remove('active');
              }
          });
          
          // Переключаем текущий дропдаун
          dropdownContent.classList.toggle('active');
      });
  });
  
  // Закрываем дропдауны при клике вне их
  document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
          document.querySelectorAll('.dropdown-content.active').forEach(item => {
              item.classList.remove('active');
          });
      }
  });
  
  // Мобильное меню
  const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  
  if (mobileMenuToggle && mobileMenu) {
      mobileMenuToggle.addEventListener('click', function() {
          mobileMenu.classList.toggle('active');
          mobileMenuToggle.classList.toggle('active');
      });
  }
  
  // Модальные окна
  const modalTriggers = document.querySelectorAll('[data-modal]');
  
  modalTriggers.forEach(trigger => {
      trigger.addEventListener('click', function(e) {
          e.preventDefault();
          const modalId = this.getAttribute('data-modal');
          const modal = document.getElementById(modalId);
          
          if (modal) {
              openModal(modal);
          }
      });
  });
  
  // Кнопки закрытия модальных окон
  const modalCloseButtons = document.querySelectorAll('.modal-close');
  
  modalCloseButtons.forEach(button => {
      button.addEventListener('click', function() {
          const modal = this.closest('.modal');
          closeModal(modal);
      });
  });
  
  // Закрытие модальных окон при клике вне их содержимого
  document.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal')) {
          closeModal(e.target);
      }
  });
  
  // Закрытие модальных окон при нажатии Escape
  document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
          const activeModal = document.querySelector('.modal.active');
          if (activeModal) {
              closeModal(activeModal);
          }
      }
  });
}

/**
* Открытие модального окна
* @param {HTMLElement} modal - Элемент модального окна
*/
function openModal(modal) {
  document.body.style.overflow = 'hidden';
  modal.classList.add('active');
  
  // Фокус на первое поле ввода
  setTimeout(() => {
      const firstInput = modal.querySelector('input, textarea, select, button:not(.modal-close)');
      if (firstInput) {
          firstInput.focus();
      }
  }, 100);
}

/**
* Закрытие модального окна
* @param {HTMLElement} modal - Элемент модального окна
*/
function closeModal(modal) {
  document.body.style.overflow = '';
  modal.classList.remove('active');
}

/**
* Настройка валидации форм
*/
function setupFormValidation() {
  const forms = document.querySelectorAll('form[data-validate]');
  
  forms.forEach(form => {
      // Обработка отправки формы
      form.addEventListener('submit', function(e) {
          // Сбрасываем предыдущие ошибки
          clearFormErrors(form);
          
          // Валидируем поля
          const isValid = validateForm(form);
          
          // Если форма невалидна, предотвращаем отправку
          if (!isValid) {
              e.preventDefault();
          }
      });
      
      // Валидация при потере фокуса
      form.querySelectorAll('input, textarea, select').forEach(field => {
          field.addEventListener('blur', function() {
              validateField(field);
          });
      });
  });
}

/**
* Валидация формы
* @param {HTMLFormElement} form - Форма для валидации
* @returns {boolean} - Результат валидации
*/
function validateForm(form) {
  let isValid = true;
  const requiredFields = form.querySelectorAll('[required]');
  
  // Проверяем все обязательные поля
  requiredFields.forEach(field => {
      if (!validateField(field)) {
          isValid = false;
      }
  });
  
  // Проверяем все поля с паттернами
  form.querySelectorAll('[pattern]').forEach(field => {
      if (field.value && !validateField(field)) {
          isValid = false;
      }
  });
  
  // Проверяем все email поля
  form.querySelectorAll('[type="email"]').forEach(field => {
      if (field.value && !validateEmail(field.value)) {
          showFieldError(field, 'Please enter a valid email address');
          isValid = false;
      }
  });
  
  // Проверяем все url поля
  form.querySelectorAll('[type="url"]').forEach(field => {
      if (field.value && !validateUrl(field.value)) {
          showFieldError(field, 'Please enter a valid URL');
          isValid = false;
      }
  });
  
  return isValid;
}

/**
* Валидация отдельного поля
* @param {HTMLElement} field - Поле для валидации
* @returns {boolean} - Результат валидации
*/
function validateField(field) {
  // Проверка обязательного поля
  if (field.hasAttribute('required') && !field.value.trim()) {
      showFieldError(field, 'This field is required');
      return false;
  }
  
  // Проверка по паттерну
  if (field.hasAttribute('pattern') && field.value) {
      const pattern = new RegExp(field.getAttribute('pattern'));
      if (!pattern.test(field.value)) {
          const errorMessage = field.getAttribute('data-error-message') || 'Please enter a valid value';
          showFieldError(field, errorMessage);
          return false;
      }
  }
  
  // Проверка минимальной длины
  if (field.hasAttribute('minlength') && field.value) {
      const minLength = parseInt(field.getAttribute('minlength'));
      if (field.value.length < minLength) {
          showFieldError(field, `Please enter at least ${minLength} characters`);
          return false;
      }
  }
  
  // Проверка максимальной длины
  if (field.hasAttribute('maxlength') && field.value) {
      const maxLength = parseInt(field.getAttribute('maxlength'));
      if (field.value.length > maxLength) {
          showFieldError(field, `Please enter no more than ${maxLength} characters`);
          return false;
      }
  }
  
  // Поле прошло валидацию
  clearFieldError(field);
  return true;
}

/**
* Валидация email
* @param {string} email - Email для проверки
* @returns {boolean} - Результат валидации
*/
function validateEmail(email) {
  const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  return re.test(String(email).toLowerCase());
}

/**
* Валидация URL
* @param {string} url - URL для проверки
* @returns {boolean} - Результат валидации
*/
function validateUrl(url) {
  try {
      new URL(url);
      return true;
  } catch (e) {
      return false;
  }
}

/**
* Отображение ошибки для поля
* @param {HTMLElement} field - Поле с ошибкой
* @param {string} message - Сообщение об ошибке
*/
function showFieldError(field, message) {
  // Очищаем предыдущую ошибку
  clearFieldError(field);
  
  // Добавляем класс ошибки
  field.classList.add('error');
  
  // Создаем элемент сообщения
  const errorElement = document.createElement('div');
  errorElement.className = 'error-message';
  errorElement.textContent = message;
  
  // Вставляем после поля
  field.parentNode.insertBefore(errorElement, field.nextSibling);
}

/**
* Очистка ошибки для поля
* @param {HTMLElement} field - Поле для очистки ошибки
*/
function clearFieldError(field) {
  field.classList.remove('error');
  
  // Удаляем сообщение об ошибке, если оно есть
  const nextElement = field.nextElementSibling;
  if (nextElement && nextElement.classList.contains('error-message')) {
      nextElement.remove();
  }
}

/**
* Очистка всех ошибок формы
* @param {HTMLFormElement} form - Форма для очистки ошибок
*/
function clearFormErrors(form) {
  form.querySelectorAll('.error').forEach(field => {
      clearFieldError(field);
  });
}

/**
* Настройка анимаций при прокрутке
*/
function setupScrollAnimations() {
  const animatedElements = document.querySelectorAll('.animate-on-scroll');
  
  if (animatedElements.length === 0) {
      return;
  }
  
  // Функция проверки видимости элемента
  function isElementInViewport(el) {
      const rect = el.getBoundingClientRect();
      return (
          rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.8
      );
  }
  
  // Проверка элементов при прокрутке
  function checkElements() {
      animatedElements.forEach(element => {
          if (isElementInViewport(element) && !element.classList.contains('animated')) {
              element.classList.add('animated');
          }
      });
  }
  
  // Обработчик события прокрутки
  window.addEventListener('scroll', checkElements);
  
  // Проверяем элементы при загрузке страницы
  checkElements();
}

// Экспорт функций для использования в других скриптах
window.openModal = openModal;
window.closeModal = closeModal;