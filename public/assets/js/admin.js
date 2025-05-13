/**
 * JavaScript для админ-панели
 * CryptoLogoWall
 */

// Ждем загрузку DOM перед выполнением скриптов
document.addEventListener('DOMContentLoaded', function() {
  // Инициализация компонентов админ-панели
  initAdminComponents();
  
  // Настройка обработчиков для таблиц с данными
  setupDataTables();
  
  // Настройка валидации форм админки
  setupAdminForms();
  
  // Обработка модальных окон подтверждения
  setupConfirmModals();
});

/**
* Инициализация компонентов админ-панели
*/
function initAdminComponents() {
  // Переключение мобильного меню
  const menuToggle = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  
  if (menuToggle && sidebar) {
      menuToggle.addEventListener('click', function() {
          sidebar.classList.toggle('active');
      });
      
      // Закрытие меню при клике вне его
      document.addEventListener('click', function(event) {
          const isClickInsideMenu = sidebar.contains(event.target);
          const isClickOnToggle = menuToggle.contains(event.target);
          
          if (!isClickInsideMenu && !isClickOnToggle && sidebar.classList.contains('active')) {
              sidebar.classList.remove('active');
          }
      });
  }
  
  // Подсветка активного пункта меню
  const currentLocation = window.location.pathname;
  const navLinks = document.querySelectorAll('.nav-link');
  
  navLinks.forEach(link => {
      const href = link.getAttribute('href');
      
      if (href && currentLocation.endsWith(href)) {
          link.classList.add('active');
      } else {
          link.classList.remove('active');
      }
  });
  
  // Инициализация тултипов
  initTooltips();
}

/**
* Инициализация тултипов
*/
function initTooltips() {
  const tooltips = document.querySelectorAll('[data-tooltip]');
  
  tooltips.forEach(tooltip => {
      tooltip.addEventListener('mouseenter', function() {
          const text = this.getAttribute('data-tooltip');
          
          if (!text) return;
          
          const tooltipElement = document.createElement('div');
          tooltipElement.className = 'admin-tooltip';
          tooltipElement.textContent = text;
          document.body.appendChild(tooltipElement);
          
          const rect = this.getBoundingClientRect();
          const tooltipRect = tooltipElement.getBoundingClientRect();
          
          tooltipElement.style.top = rect.top - tooltipRect.height - 10 + 'px';
          tooltipElement.style.left = rect.left + (rect.width / 2) - (tooltipRect.width / 2) + 'px';
          
          tooltip.addEventListener('mouseleave', function onLeave() {
              tooltipElement.remove();
              tooltip.removeEventListener('mouseleave', onLeave);
          });
      });
  });
}

/**
* Настройка обработчиков для таблиц с данными
*/
function setupDataTables() {
  const tables = document.querySelectorAll('.admin-table');
  
  tables.forEach(table => {
      // Поиск по таблице
      const searchInput = table.parentElement.querySelector('.table-search');
      
      if (searchInput) {
          searchInput.addEventListener('input', function() {
              const searchText = this.value.toLowerCase();
              const rows = table.querySelectorAll('tbody tr');
              
              rows.forEach(row => {
                  const text = row.textContent.toLowerCase();
                  row.style.display = text.includes(searchText) ? '' : 'none';
              });
          });
      }
      
      // Сортировка по столбцам
      const sortHeaders = table.querySelectorAll('th[data-sort]');
      
      sortHeaders.forEach(header => {
          header.addEventListener('click', function() {
              const sortKey = this.getAttribute('data-sort');
              const isAscending = this.classList.contains('sort-asc');
              
              // Сбрасываем сортировку для всех столбцов
              sortHeaders.forEach(h => {
                  h.classList.remove('sort-asc', 'sort-desc');
              });
              
              // Устанавливаем новое направление сортировки
              this.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
              
              // Сортируем таблицу
              sortTable(table, sortKey, !isAscending);
          });
      });
      
      // Пагинация для таблицы
      const perPage = table.getAttribute('data-per-page');
      
      if (perPage) {
          initTablePagination(table, parseInt(perPage));
      }
  });
}

/**
* Сортировка таблицы
* @param {HTMLElement} table - Таблица для сортировки
* @param {string} key - Ключ сортировки (атрибут data-value в ячейках)
* @param {boolean} ascending - Направление сортировки
*/
function sortTable(table, key, ascending) {
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  // Сортируем строки
  rows.sort((a, b) => {
      const aCell = a.querySelector(`td[data-${key}]`);
      const bCell = b.querySelector(`td[data-${key}]`);
      
      if (!aCell || !bCell) return 0;
      
      const aValue = aCell.getAttribute(`data-${key}`) || aCell.textContent.trim();
      const bValue = bCell.getAttribute(`data-${key}`) || bCell.textContent.trim();
      
      // Проверяем, являются ли значения числами
      const aNum = Number(aValue);
      const bNum = Number(bValue);
      
      if (!isNaN(aNum) && !isNaN(bNum)) {
          return ascending ? aNum - bNum : bNum - aNum;
      }
      
      // Сортировка строк
      return ascending ? 
          aValue.localeCompare(bValue) : 
          bValue.localeCompare(aValue);
  });
  
  // Перестраиваем таблицу
  rows.forEach(row => {
      tbody.appendChild(row);
  });
}

/**
* Инициализация пагинации для таблицы
* @param {HTMLElement} table - Таблица
* @param {number} perPage - Количество строк на страницу
*/
function initTablePagination(table, perPage) {
  const rows = table.querySelectorAll('tbody tr');
  const totalRows = rows.length;
  
  if (totalRows <= perPage) {
      return;
  }
  
  const totalPages = Math.ceil(totalRows / perPage);
  let currentPage = 1;
  
  // Создаем контейнер пагинации
  const paginationContainer = document.createElement('div');
  paginationContainer.className = 'table-pagination';
  table.parentNode.insertBefore(paginationContainer, table.nextSibling);
  
  // Функция отображения строк для текущей страницы
  function showPage(page) {
      const start = (page - 1) * perPage;
      const end = start + perPage;
      
      rows.forEach((row, index) => {
          row.style.display = (index >= start && index < end) ? '' : 'none';
      });
      
      updatePagination();
  }
  
  // Функция обновления контролов пагинации
  function updatePagination() {
      paginationContainer.innerHTML = '';
      
      // Кнопка "Предыдущая"
      const prevButton = document.createElement('button');
      prevButton.className = 'pagination-btn prev';
      prevButton.innerHTML = '&laquo;';
      prevButton.disabled = currentPage === 1;
      prevButton.addEventListener('click', () => {
          if (currentPage > 1) {
              currentPage--;
              showPage(currentPage);
          }
      });
      paginationContainer.appendChild(prevButton);
      
      // Номера страниц
      for (let i = 1; i <= totalPages; i++) {
          const pageButton = document.createElement('button');
          pageButton.className = 'pagination-btn page';
          pageButton.textContent = i;
          
          if (i === currentPage) {
              pageButton.classList.add('active');
          }
          
          pageButton.addEventListener('click', () => {
              currentPage = i;
              showPage(currentPage);
          });
          
          paginationContainer.appendChild(pageButton);
      }
      
      // Кнопка "Следующая"
      const nextButton = document.createElement('button');
      nextButton.className = 'pagination-btn next';
      nextButton.innerHTML = '&raquo;';
      nextButton.disabled = currentPage === totalPages;
      nextButton.addEventListener('click', () => {
          if (currentPage < totalPages) {
              currentPage++;
              showPage(currentPage);
          }
      });
      paginationContainer.appendChild(nextButton);
  }
  
  // Показываем первую страницу
  showPage(currentPage);
}

/**
* Настройка валидации форм админки
*/
function setupAdminForms() {
  const forms = document.querySelectorAll('form.admin-form');
  
  forms.forEach(form => {
      form.addEventListener('submit', function(e) {
          const required = form.querySelectorAll('[required]');
          let isValid = true;
          
          // Проверяем все обязательные поля
          required.forEach(field => {
              if (!field.value.trim()) {
                  isValid = false;
                  field.classList.add('error');
                  
                  // Показываем сообщение об ошибке
                  const errorMessage = field.getAttribute('data-error') || 'This field is required';
                  showFieldError(field, errorMessage);
              } else {
                  field.classList.remove('error');
                  clearFieldError(field);
              }
          });
          
          if (!isValid) {
              e.preventDefault();
          }
      });
  });
}

/**
* Отображение ошибки для поля
* @param {HTMLElement} field - Поле с ошибкой
* @param {string} message - Сообщение об ошибке
*/
function showFieldError(field, message) {
  // Очищаем предыдущую ошибку
  clearFieldError(field);
  
  // Создаем элемент сообщения
  const errorElement = document.createElement('div');
  errorElement.className = 'field-error';
  errorElement.textContent = message;
  
  // Вставляем после поля
  field.parentNode.insertBefore(errorElement, field.nextSibling);
}

/**
* Очистка ошибки для поля
* @param {HTMLElement} field - Поле для очистки ошибки
*/
function clearFieldError(field) {
  const nextElement = field.nextElementSibling;
  if (nextElement && nextElement.classList.contains('field-error')) {
      nextElement.remove();
  }
}

/**
* Обработка модальных окон подтверждения
*/
function setupConfirmModals() {
  const confirmButtons = document.querySelectorAll('[data-confirm]');
  
  confirmButtons.forEach(button => {
      button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const message = this.getAttribute('data-confirm');
          if (confirm(message)) {
              // Если кнопка внутри формы, отправляем форму
              const form = this.closest('form');
              if (form) {
                  form.submit();
              } else {
                  // Иначе переходим по ссылке
                  window.location.href = this.getAttribute('href');
              }
          }
      });
  });
}