import React from 'react';
import ReactDOM from 'react-dom/client';
import Header from '@components/Header';
import Footer from '@components/Footer';
import { getAppData } from '@utils/index';
import '@/styles/index.css';

/**
 * Entry point de la aplicación React
 * Este archivo monta los componentes React en los divs preparados por Blade
 */

// Obtener datos pasados desde Blade
const appData = getAppData('react-app-data');

if (!appData) {
  console.error('No se pudieron cargar los datos de la aplicación desde Blade');
} else {
  console.log('MuseDock React Theme loaded successfully', {
    settings: appData.settings,
    currentLang: appData.currentLang,
    menu: appData.menu,
  });

  // Montar Header si existe el contenedor
  const headerContainer = document.getElementById('react-header');
  if (headerContainer) {
    const headerRoot = ReactDOM.createRoot(headerContainer);
    headerRoot.render(
      <React.StrictMode>
        <Header
          menu={appData.menu}
          settings={appData.settings}
          transparent={appData.settings.header_transparent ?? false}
          sticky={appData.settings.header_sticky ?? true}
        />
      </React.StrictMode>
    );
  }

  // Montar Footer si existe el contenedor
  const footerContainer = document.getElementById('react-footer');
  if (footerContainer) {
    const footerRoot = ReactDOM.createRoot(footerContainer);
    footerRoot.render(
      <React.StrictMode>
        <Footer
          settings={appData.settings}
          languages={appData.languages}
          currentLang={appData.currentLang}
        />
      </React.StrictMode>
    );
  }

  // Ejecutar código personalizado del usuario si existe
  const customJS = appData.settings.custom_js;
  if (customJS) {
    try {
      // eslint-disable-next-line no-eval
      eval(customJS);
    } catch (error) {
      console.error('Error ejecutando JavaScript personalizado:', error);
    }
  }
}

// Agregar botones de copiar al código (solo una vez, sin observer)
function addCopyButtons() {
  const preBlocks = document.querySelectorAll('pre:not(.code-processed)');

  preBlocks.forEach((pre) => {
    // Marcar como procesado para evitar duplicados
    pre.classList.add('code-processed');

    const code = pre.querySelector('code');
    if (!code) return;

    // Crear contenedor del botón debajo del bloque
    const buttonContainer = document.createElement('div');
    buttonContainer.style.cssText = `
      display: flex;
      justify-content: flex-end;
      margin-top: 8px;
      margin-bottom: 1rem;
    `;

    // Crear botón de copiar
    const button = document.createElement('button');
    button.className = 'copy-code-button';
    button.innerHTML = '<i class="fas fa-copy"></i> Copiar código';
    button.setAttribute('aria-label', 'Copiar código');
    button.style.cssText = `
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: #fff;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    `;

    button.addEventListener('mouseenter', () => {
      button.style.transform = 'translateY(-2px)';
      button.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.4)';
    });

    button.addEventListener('mouseleave', () => {
      button.style.transform = 'translateY(0)';
      button.style.boxShadow = '0 2px 8px rgba(102, 126, 234, 0.3)';
    });

    button.addEventListener('click', async () => {
      const codeText = code.textContent || '';

      try {
        await navigator.clipboard.writeText(codeText);

        // Estado de éxito
        button.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        button.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        button.style.boxShadow = '0 2px 8px rgba(16, 185, 129, 0.3)';

        setTimeout(() => {
          button.innerHTML = '<i class="fas fa-copy"></i> Copiar código';
          button.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
          button.style.boxShadow = '0 2px 8px rgba(102, 126, 234, 0.3)';
        }, 2000);
      } catch (err) {
        console.error('Error al copiar:', err);

        // Estado de error
        button.innerHTML = '<i class="fas fa-times"></i> Error';
        button.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';

        setTimeout(() => {
          button.innerHTML = '<i class="fas fa-copy"></i> Copiar código';
          button.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }, 2000);
      }
    });

    buttonContainer.appendChild(button);

    // Insertar el botón después del bloque pre
    pre.parentNode?.insertBefore(buttonContainer, pre.nextSibling);
  });
}

// Ejecutar al cargar el DOM
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', addCopyButtons);
} else {
  addCopyButtons();
}

// Scroll to top button
function createScrollToTopButton() {
  const button = document.createElement('button');
  button.id = 'scroll-to-top';
  button.innerHTML = '<i class="fas fa-arrow-up"></i>';
  button.className =
    'fixed bottom-8 right-8 w-12 h-12 bg-primary-600 text-white rounded-full shadow-lg hover:bg-primary-700 transition-all duration-300 opacity-0 invisible z-50';
  button.setAttribute('aria-label', 'Scroll to top');

  button.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  document.body.appendChild(button);

  window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
      button.classList.remove('opacity-0', 'invisible');
    } else {
      button.classList.add('opacity-0', 'invisible');
    }
  });
}

createScrollToTopButton();

// Exponer API global para que Blade pueda interactuar con React
(window as any).MuseDockReact = {
  version: '1.0.0',
  appData,
  reload: () => window.location.reload(),
};

export {};
