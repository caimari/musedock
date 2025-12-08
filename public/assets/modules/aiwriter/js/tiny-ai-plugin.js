/**
 * TinyMCE AI Writer Plugin
 * Integra capacidades de IA con TinyMCE
 */
tinymce.PluginManager.add('aiwriter', function(editor, url) {
  
  // Añadir botón a la barra de herramientas
  editor.ui.registry.addButton('aiwriter', {
    icon: 'bot',
    tooltip: 'AI Writer',
    onAction: function() {
      openAiDialog();
    }
  });
  
  // Añadir opción al menú contextual
  editor.ui.registry.addMenuItem('aiwriter', {
    text: 'AI Writer',
    icon: 'bot',
    context: 'tools',
    onAction: function() {
      openAiDialog();
    }
  });
  
  // Función para abrir el diálogo de IA
  function openAiDialog() {
    const selectedText = editor.selection.getContent({format: 'text'});
    
    editor.windowManager.open({
      title: 'AI Writer',
      size: 'large',
      body: {
        type: 'panel',
        items: [
          {
            type: 'selectbox',
            name: 'action',
            label: 'Acción',
            items: [
              {text: 'Generar contenido', value: 'generate'},
              {text: 'Mejorar texto seleccionado', value: 'improve'},
              {text: 'Resumir texto', value: 'summarize'},
              {text: 'Corregir texto', value: 'correct'},
              {text: 'Generar ideas de título', value: 'titles'}
            ]
          },
          {
            type: 'selectbox',
            name: 'provider_id',
            label: 'Proveedor de IA',
            items: []  // Se rellenará con AJAX
          },
          {
            type: 'textarea',
            name: 'prompt',
            label: 'Prompt / Instrucciones',
            placeholder: 'Describe lo que quieres generar o cómo mejorar el texto...'
          },
          {
            type: 'checkbox',
            name: 'replace',
            label: 'Reemplazar texto seleccionado',
            enabled: selectedText.length > 0
          }
        ]
      },
      buttons: [
        {
          type: 'cancel',
          text: 'Cancelar'
        },
        {
          type: 'submit',
          text: 'Generar',
          primary: true
        }
      ],
      initialData: {
        action: 'generate',
        prompt: '',
        provider_id: '',
        replace: selectedText.length > 0,
        selectedText: selectedText
      },
      onSubmit: function(api) {
        const data = api.getData();
        
        // Mostrar indicador de carga
        api.block('Generando contenido con IA...');
        
        // Llamada AJAX al endpoint del servidor
        fetch('/api/ai/generate', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            action: data.action,
            provider_id: data.provider_id,
            prompt: data.prompt,
            text: selectedText,
            module: 'aiwriter',
            // Datos específicos para cada acción
            ...(data.action === 'generate' && { action: 'generate' }),
            ...(data.action === 'improve' && { action: 'improve' }),
            ...(data.action === 'summarize' && { action: 'summarize' }),
            ...(data.action === 'correct' && { action: 'correct' }),
            ...(data.action === 'titles' && { action: 'titles' })
          })
        })
        .then(response => response.json())
        .then(result => {
          // Desbloquear la interfaz
          api.unblock();
          
          if (result.success) {
            // Insertar el contenido generado
            if (data.replace && selectedText.length > 0) {
              editor.selection.setContent(result.content);
            } else {
              editor.insertContent(result.content);
            }
            api.close();
            
            // Mostrar notificación de éxito con detalles de uso
            editor.notificationManager.open({
              text: `Contenido generado correctamente (${result.usage.tokens} tokens)`,
              type: 'success',
              timeout: 3000
            });
          } else {
            // Mostrar error
            editor.notificationManager.open({
              text: result.message || 'Error al generar contenido',
              type: 'error',
              timeout: 3000
            });
          }
        })
        .catch(error => {
          api.unblock();
          editor.notificationManager.open({
            text: 'Error de conexión: ' + error.message,
            type: 'error',
            timeout: 3000
          });
        });
      }
    });
    
    // Cargar proveedores disponibles mediante AJAX
    fetch('/api/ai/providers')
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          const providers = result.providers;
          const items = providers.map(p => ({text: p.name, value: p.id.toString()}));
          
          // Actualizar el selectbox con los proveedores
          const dialogApi = editor.windowManager.getTopMostWindow();
          if (dialogApi) {
            dialogApi.setData({provider_id: items[0]?.value || ''});
          }
        }
      })
      .catch(error => {
        editor.notificationManager.open({
          text: 'Error al cargar proveedores: ' + error.message,
          type: 'error',
          timeout: 3000
        });
      });
  }
  
  // Añadir menú desplegable con acciones rápidas
  editor.ui.registry.addMenuButton('aiwritermenu', {
    icon: 'bot',
    tooltip: 'AI Writer',
    fetch: function(callback) {
      const items = [
        {
          type: 'menuitem',
          text: 'Generar párrafo',
          onAction: function() {
            quickAction('generate', 'Genera un párrafo sobre ', '');
          }
        },
        {
          type: 'menuitem',
          text: 'Completar idea',
          onAction: function() {
            quickAction('continue', '', editor.selection.getContent({format: 'text'}));
          }
        },
        {
          type: 'menuitem',
          text: 'Mejorar redacción',
          onAction: function() {
            quickAction('improve', '', editor.selection.getContent({format: 'text'}));
          }
        },
        {
          type: 'menuitem',
          text: 'Corregir errores',
          onAction: function() {
            quickAction('correct', '', editor.selection.getContent({format: 'text'}));
          }
        },
        {
          type: 'menuitem',
          text: 'Más opciones...',
          onAction: function() {
            openAiDialog();
          }
        }
      ];
      callback(items);
    }
  });
  
  // Función para acciones rápidas
  function quickAction(action, prefixPrompt, text) {
    // Si no hay texto seleccionado para acciones que lo requieren
    if ((action === 'improve' || action === 'correct' || action === 'continue') && !text) {
      editor.notificationManager.open({
        text: 'Selecciona el texto que deseas procesar',
        type: 'warning',
        timeout: 2000
      });
      return;
    }
    
    // Para acción de generación sin texto
    if (action === 'generate' && !prefixPrompt.trim()) {
      // Abrir mini-prompt
      editor.windowManager.open({
        title: 'Generar con IA',
        body: {
          type: 'panel',
          items: [{
            type: 'input',
            name: 'topic',
            label: 'Tema o idea'
          }]
        },
        buttons: [
          {
            type: 'cancel',
            text: 'Cancelar'
          },
          {
            type: 'submit',
            text: 'Generar',
            primary: true
          }
        ],
        onSubmit: function(api) {
          const topic = api.getData().topic;
          api.close();
          processAiRequest(action, prefixPrompt + topic, '', true);
        }
      });
      return;
    }
    
    // Procesar directamente
    processAiRequest(action, prefixPrompt, text, true);
  }
  
  // Función para procesar peticiones rápidas de IA
  function processAiRequest(action, prompt, text, replace) {
    editor.notificationManager.open({
      text: 'Procesando con IA...',
      type: 'info',
      timeout: 1000
    });
    
    fetch('/api/ai/quick', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({
        action: action,
        prompt: prompt,
        text: text,
        module: 'aiwriter'
      })
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        if (replace && text) {
          editor.selection.setContent(result.content);
        } else {
          editor.insertContent(result.content);
        }
        
        // Mostrar notificación de éxito con detalles de uso
        editor.notificationManager.open({
          text: `Contenido generado correctamente (${result.usage.tokens} tokens)`,
          type: 'success',
          timeout: 3000
        });
      } else {
        editor.notificationManager.open({
          text: result.message || 'Error al procesar con IA',
          type: 'error',
          timeout: 3000
        });
      }
    })
    .catch(error => {
      editor.notificationManager.open({
        text: 'Error de conexión: ' + error.message,
        type: 'error',
        timeout: 3000
      });
    });
  }
  
  // Devolver los valores de configuración del plugin
  return {
    getMetadata: function() {
      return {
        name: 'AI Writer',
        url: 'https://musedock.net/modules/aiwriter'
      };
    }
  };
});