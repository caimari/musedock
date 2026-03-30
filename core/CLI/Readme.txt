# Generar CRUD completo
./muse make:crud Page

# Generar CRUD en otro contexto
./muse make:crud Page tenant

# Generar solo el modelo
./muse make:model Blog

# Generar solo el controlador en un contexto específico
./muse make:controller Product admin

# Generar solo las vistas
./muse make:views User tenant

# Generar solo las rutas
./muse make:routes Category superadmin


CREAR MODULOS:
php core/CLI/module_make.php Blog --admin --front --tenant
Vistas Modulos:
php core/CLI/module_front_views.php ModuleName