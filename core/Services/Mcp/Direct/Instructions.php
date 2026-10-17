<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

/** Server guidance delivered during MCP initialization, scoped to this access. */
final class Instructions
{
    public static function forAccess(Tools $tools,array $auth): string
    {
        $names=array_column($tools->catalog($auth),'name');
        $lines=[
            'MuseDock CMS: utiliza solo los sitios y operaciones autorizados de esta conexión. Estas instrucciones describen herramientas; no conceden permisos ni sustituyen la orden del usuario.',
            'Antes de crear, consulta artículos existentes y confirma el sitio. Los aliases son nombres del mismo site_id, no otros sitios. No presupongas que un subdominio carece de dominio público.',
        ];
        if (in_array('create_and_publish_article',$names,true)) $lines[]='FLUJO PREFERIDO para un artículo NUEVO con portada y publicación: create_and_publish_article con title, content HTML, SEO, taxonomías, prompt de imagen, idempotency_key estable y apply=true. El CMS continúa en segundo plano aunque termine el chat. No uses primero create_post si el encargo ya es crear y publicar con portada. mode=draft conserva el borrador con portada. Requiere aprobaciones automáticas y permisos suficientes; no los eludas.';
        if (in_array('publish_article_with_image',$names,true)) $lines[]='Para un artículo YA CREADO: publish_article_with_image con post_id, site_id si procede, prompt y apply=true. No crees otro artículo. Coordina generación, asignación y publicación. Consulta esta misma herramienta con action=status y el mismo post_id; action=resume con apply=true reanuda una etapa recuperable.';
        if (in_array('create_and_publish_article',$names,true)) $lines[]='Para consultar create_and_publish_article utiliza la MISMA herramienta con action=status, job_id y site_id si procede. Para reanudar, action=resume, job_id y apply=true. Mantén la misma idempotency_key para el mismo encargo. No envíes un job_id a get_request_status.';
        if (in_array('get_request_status',$names,true)) $lines[]='Las solicitudes simples usan request_id y se consultan con get_request_status. apply=false solo prepara un plan; apply=true solicita cambios y puede aprobarlos automáticamente según configuración. queued no significa completado. recovered=true indica recuperación de una creación confirmada, no una nueva publicación.';
        if (in_array('create_post',$names,true)) $lines[]='create_post/update_post sirven para operaciones editoriales directas y documentación sin imagen. create_post con status=draft solo crea un borrador: no encadena portada ni publicación. Para documentación usa post_type=docs y category_ids de Documentación → Producto → Sección. Publicar sin portada depende de la política del recurso.';
        if (in_array('save_translation',$names,true)) $lines[]='Traducciones: lee el HTML completo con get_post_details/get_post y consulta list_translations. Guarda con save_translation vinculada al mismo post_id: locale, title, content HTML, SEO y slug opcional en el idioma traducido. No crees otro artículo. Conserva las aprobaciones; omitir slug conserva la ruta. No cambies una URL traducida existente sin gestionar su redirección.';
        if (in_array('generate_image',$names,true)) $lines[]='Imágenes: consulta list_image_options antes de generar; no inventes tamaños o calidades. Respeta las preferencias del sitio y portadas sin texto, letras, números, logotipos ni marcas de agua en los flujos coordinados. No lances generate_image aparte de un trabajo que ya genera su portada. Una generación incierta no debe repetirse automáticamente: revisa trabajo y medios.';
        $lines[]='Lee content en structuredContent o en el JSON del bloque de texto; ambos contienen el mismo resultado. Una presentación vacía del conector no prueba que el CMS haya devuelto contenido vacío.';
        $lines[]='No afirmes enviado, en cola, creado o publicado sin la respuesta real y su identificador. Si un ID no se encuentra, conserva el error y comprueba artículos/trabajos antes de repetir. Informa el estado final confirmado, post_id, media_id y public_url cuando existan. No inventes resultados ni prometas que recibirás automáticamente el resultado después de terminar el chat.';
        $lines[]='Herramientas disponibles para este acceso (sus esquemas definen los argumentos): '.implode(', ',$names).'.';
        return implode("\n\n",$lines);
    }
}
