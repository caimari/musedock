<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;

class MenuItem
{
    protected $table = 'site_menu_items';
    protected $fillable = ['menu_id', 'parent', 'title', 'link', 'sort', 'depth', 'type', 'target'];

    public static function create(array $data)
    {
        // Asegurarnos que los valores sean correctos
        $menuId = $data['menu_id'] ?? null;
        $parent = $data['parent'] ?? null; // Puede ser null
        $title = $data['title'] ?? '';
        $link = $data['link'] ?? '';
        $sort = $data['sort'] ?? 0;
        $depth = $data['depth'] ?? 0;
        $type = $data['type'] ?? null;
        $target = $data['target'] ?? null;

        // Conectar a la base de datos
        $pdo = Database::connect();
        
        // Crear el SQL para la inserción
        $sql = "INSERT INTO site_menu_items 
                (menu_id, parent, title, link, sort, depth, type, target, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        try {
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $menuId,
                $parent,
                $title,
                $link,
                $sort,
                $depth,
                $type,
                $target
            ]);
            
            if (!$result) {
                error_log('Error en statement execute: ' . json_encode($stmt->errorInfo()));
                return null;
            }
            
            $id = $pdo->lastInsertId();
            
            // Crear objeto con los datos insertados
            $item = new self();
            $item->id = $id;
            $item->menu_id = $menuId;
            $item->parent = $parent;
            $item->title = $title;
            $item->link = $link;
            $item->sort = $sort;
            $item->depth = $depth;
            $item->type = $type;
            $item->target = $target;
            
            return $item;
        } catch (\PDOException $e) {
            error_log('Error PDO al insertar MenuItem: ' . $e->getMessage());
            return null;
        }
    }

    public static function where($column, $operator, $value = null)
    {
        // Si solo se pasaron dos argumentos, asumimos que el operador es '='
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $pdo = Database::connect();
        $sql = "SELECT * FROM site_menu_items WHERE $column $operator ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value]);
        
        return new MenuItemQuery($stmt);
    }
}

// Clase auxiliar para encadenar operaciones
class MenuItemQuery
{
    protected $stmt;
    
    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }
    
    public function get()
    {
        return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function delete()
    {
        $pdo = Database::connect();
        $items = $this->get();
        
        if (empty($items)) {
            return true; // Nada que eliminar
        }
        
        $ids = array_column($items, 'id');
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        
        $sql = "DELETE FROM site_menu_items WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($ids);
    }
}