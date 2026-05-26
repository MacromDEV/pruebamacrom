<?php
    require_once $_SERVER['DOCUMENT_ROOT'] . "/core/bootstrap.php";
    require_once "../../../conf.php";
    require_once "../../../tv-admin/asset/Clases/dbconectar.php";
    require_once "../../../tv-admin/asset/Clases/ConexionMySQL.php";

    class Catalogo{
        private $redis = null;
        private $conn;
        private $stats = [
            'cache_hits' => 0,
            'cache_miss' => 0
        ];
        private $jsonData = array("Bandera"=>false, "Mensaje"=>"", "Data"=>array());
        private $usarBusquedaRelajada = false;

        public function __construct($array) {
            $this->conn = new HelperMySql($array["server"], $array["user"], $array["pass"], $array["db"]);
            if (defined('USE_REDIS_CACHE') && USE_REDIS_CACHE && class_exists('Redis')) {
                try {
                    $this->redis = new Redis();
                    $this->redis->connect(REDIS_HOST, REDIS_PORT, 1.5);
                } catch (Exception $e) {
                    $this->redis = null;
                }
            }  
        }
    
        public function __destruct() {
            unset($this->conn);
        }

        // =======================================================
        // CACHÉ OPTIMIZADA: Factor aleatorio (Jitter)
        // =======================================================
        private function getTTL(string $group): int
        {
            $map = [
                'cache_existencias'   => 60,
                'cache_refacciones'   => 150,
                'cache_trefacciones'  => 150,
                'cache_ofertas'       => 300, 
                'cache_nuevos'        => 450,
                'cache_producto'      => 900,
                'cache_marcas'        => 86400,
                'cache_modelos'       => 86400,
                'cache_categorias'    => 86400,
            ];

            $baseTTL = $map[$group] ?? (defined('CACHE_TTL') ? CACHE_TTL : 150); 
            
            $jitter = (int)($baseTTL * 0.05); 
            return $baseTTL + rand(-$jitter, $jitter);
        }

        private function normalizarFiltros() {
            if (isset($this->formulario["marca"]) && $this->formulario["marca"] === "") unset($this->formulario["marca"]);
            if (isset($this->formulario["vehiculo"]) && $this->formulario["vehiculo"] === "") unset($this->formulario["vehiculo"]);

            if (!isset($this->formulario["marca"])) {
                unset($this->formulario["vehiculo"]);
                return;
            }

            if (isset($this->formulario["vehiculo"])) {
                $vehiculos = array_filter(array_map('intval', explode(',', $this->formulario["vehiculo"])));

                if (empty($vehiculos)) {
                    unset($this->formulario["vehiculo"]);
                    return;
                }

                $sql = "SELECT _id FROM u619477378_macromau.Modelos WHERE _idMarca IN ({$this->formulario["marca"]}) AND _id IN (" . implode(',', $vehiculos) . ")";
                $rows = $this->conn->fetch_all($this->conn->query($sql));

                if (empty($rows)) {
                    unset($this->formulario["vehiculo"]);
                    return;
                }
                $vehiculosValidos = array_column($rows, '_id');
                $this->formulario["vehiculo"] = implode(',', $vehiculosValidos);
            }
        }

        private function buildCacheKey(array $keys) {
            $data = [];
            foreach ($keys as $key) {
                if (!isset($this->formulario[$key]) || $this->formulario[$key] === '') {
                    $data[$key] = '';
                    continue;
                }
                $parts = explode(',', $this->formulario[$key]);
                $parts = array_filter($parts, 'strlen');
                sort($parts, SORT_STRING);
                $data[$key] = implode(',', $parts);
            }
            return md5(json_encode($data));
        }

        private function getCache($group, $key) {
            if ($this->redis) {
                $prefix = defined('REDIS_PREFIX') ? REDIS_PREFIX : 'macrom_';
                $redisKey = $prefix . $group . ':' . $key;
                $data = $this->redis->get($redisKey);
                if ($data !== false) {
                    $this->stats['cache_hits']++;
                    return unserialize($data);
                }
            }
            
            $this->stats['cache_miss']++;
            return null;
        }

        private function setCache($group, $key, $data) {
            if ($this->redis) {
                $prefix = defined('REDIS_PREFIX') ? REDIS_PREFIX : 'macrom_';
                $redisKey = $prefix . $group . ':' . $key;
                $ttl = $this->getTTL($group);
                $this->redis->setex($redisKey, $ttl, serialize($data));
            }
        }

        private function setCacheWithTags($group, $key, $data, array $tags = []) {
            if (!$this->redis) {
                return;
            }
            $prefix = defined('REDIS_PREFIX') ? REDIS_PREFIX : 'macrom_';
            $redisKey = $prefix . $group . ':' . $key;
            $ttl = $this->getTTL($group);
            $this->redis->setex($redisKey, $ttl, serialize($data));

            foreach ($tags as $tag) {
                $tagKey = $prefix . 'tag:' . $tag;
                $this->redis->sAdd($tagKey, $redisKey);
                $this->redis->expire($tagKey, $ttl);
            }
        }

        public function invalidateByTag(string $tag) {
            if (!$this->redis) return;
            $prefix = defined('REDIS_PREFIX') ? REDIS_PREFIX : 'macrom_';
            $tagKey = $prefix . 'tag:' . $tag;
            $keys = $this->redis->sMembers($tagKey);
            
            if (!empty($keys)) {
                if (method_exists($this->redis, 'unlink')) {
                    $this->redis->unlink($keys); 
                } else {
                    $this->redis->del($keys);
                }
            }
            $this->redis->del($tagKey);
        }

        private function buildCondicionesSQL(array $ignorar = []): string {
            $f = $this->formulario;
            $sql = "";

            if (!in_array('marca', $ignorar) && !empty($f['marca'])) {
                $sql .= " AND P._idMarca IN({$f['marca']})";
                if (!in_array('vehiculo', $ignorar) && !empty($f['vehiculo'])) {
                    $sql .= " AND P.Modelo IN({$f['vehiculo']})";
                }
            }

            if (!in_array('categoria', $ignorar) && !empty($f['categoria']) && $f['categoria'] !== "T") {
                $sql .= " AND P._idCategoria IN({$f['categoria']})";
            }

            if (!in_array('proveedor', $ignorar) && !empty($f['proveedor'])) {
                $sql .= " AND P.id_proveedor IN({$f['proveedor']})";
            }

            if (!in_array('disponibilidad', $ignorar) && !empty($f['disponibilidad'])) {
                if (strpos($f['disponibilidad'], 'xistencia') !== false) {
                    $sql .= " AND P.stock >= 1";
                }
                if (strpos($f['disponibilidad'], 'ferta') !== false) {
                    $sql .= " AND P.RefaccionOferta = 1";
                }
                if (strpos($f['disponibilidad'], 'Nuevos') !== false) {
                    $sql .= " AND P.RefaccionNueva = 1";
                }
                if (strpos($f['disponibilidad'], 'gratis') !== false) {
                    $sql .= " AND P.Enviogratis = 1";
                }
            }

            $sql .= $this->buildWhereBusquedaSQL();
            return $sql;
        }

        private function analizarBusquedaInteligente() {
            $busquedaOriginal = $this->formulario["producto"] ?? "";
            if (empty(trim($busquedaOriginal))) {
                return ["texto_limpio" => "", "filtros" => []];
            }

            $textoLimpio = " " . mb_strtolower(trim($busquedaOriginal), 'UTF-8') . " ";
            $filtrosDetectados = ['marca' => [], 'vehiculo' => [], 'proveedor' => [], 'categoria' => []];
            
            $sqlMarcas = "SELECT _id, LOWER(Marca) as nombre FROM u619477378_macromau.Marcas WHERE Estatus = 1";
            foreach($this->conn->fetch_all($this->conn->query($sqlMarcas)) as $m) {
                $nom = trim(mb_strtolower($m['nombre'], 'UTF-8'));
                if (empty($nom)) continue;
                if (preg_match('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', $textoLimpio)) {
                    $filtrosDetectados['marca'][] = $m['_id'];
                    $textoLimpio = preg_replace('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', ' ', $textoLimpio);
                }
            }
            $sqlModelos = "SELECT _id, LOWER(Modelo) as nombre, _idMarca FROM u619477378_macromau.Modelos WHERE Estatus = 1";
            foreach($this->conn->fetch_all($this->conn->query($sqlModelos)) as $mo) {
                $nom = trim(mb_strtolower($mo['nombre'], 'UTF-8'));
                if (empty($nom)) continue;
                if (preg_match('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', $textoLimpio)) {
                    $filtrosDetectados['vehiculo'][] = $mo['_id'];

                    if(!in_array($mo['_idMarca'], $filtrosDetectados['marca'])) {
                        $filtrosDetectados['marca'][] = $mo['_idMarca'];
                    }
                    $textoLimpio = preg_replace('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', ' ', $textoLimpio);
                }
            }
            $sqlCat = "SELECT _id, LOWER(Categoria) as nombre FROM u619477378_macromau.Categorias WHERE Status = 1";
            foreach($this->conn->fetch_all($this->conn->query($sqlCat)) as $c) {
                $nom = trim(mb_strtolower($c['nombre'], 'UTF-8'));
                if (empty($nom)) continue;
                if (preg_match('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', $textoLimpio)) {
                    $filtrosDetectados['categoria'][] = $c['_id'];
                    $textoLimpio = preg_replace('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', ' ', $textoLimpio);
                }
            }
            $sqlProv = "SELECT _id, LOWER(Proveedor) as nombre FROM u619477378_macromau.Proveedor WHERE Estatus = 1";
            foreach($this->conn->fetch_all($this->conn->query($sqlProv)) as $p) {
                $nom = trim(mb_strtolower($p['nombre'], 'UTF-8'));
                if (empty($nom)) continue;
                if (preg_match('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', $textoLimpio)) {
                    $filtrosDetectados['proveedor'][] = $p['_id'];
                    $textoLimpio = preg_replace('/(?<=\s)'.preg_quote($nom, '/').'(?=\s)/iu', ' ', $textoLimpio);
                }
            }

            return [
                "texto_limpio" => trim(preg_replace('/\s+/', ' ', $textoLimpio)),
                "filtros" => [
                    "armadora" => implode(',', array_unique($filtrosDetectados['marca'])),
                    "mdl" => implode(',', array_unique($filtrosDetectados['vehiculo'])),
                    "proveedor" => implode(',', array_unique($filtrosDetectados['proveedor'])),
                    "cate" => implode(',', array_unique($filtrosDetectados['categoria']))
                ]
            ];
        }

        public function principal(){
            $this->methodo = $_SERVER['REQUEST_METHOD'];
            switch($this->methodo){
                case 'GET':
                    $this->formulario = array_map("htmlspecialchars", $_GET);
                    $this->normalizarFiltros();
                    switch($this->formulario["opc"]){
                        case 'Buscar':
                            switch($this->formulario["tipo"]){
                                case 'Categorias':
                                    $this->jsonData["Bandera"] = true;
                                    $this->jsonData["Mensaje"] = "Entro a la seccion de categorias";
                                    $this->jsonData["Data"]["Categorias"] = $this->getCategorias();
                                    $this->jsonData["Data"]["Marcas"] = $this->getMarcas();
                                    $this->jsonData["Data"]["Proveedores"] = $this->getProveedores();
                                    $this->jsonData["Data"]["Existencias"] = $this->getExistencias();
                                    $this->jsonData["Data"]["Ofertas"] = $this->getOfertas();
                                    $this->jsonData["Data"]["Nuevos"] = $this->getNuevos();
                                    if(isset($this->formulario["marca"]) and strlen($this->formulario["marca"])!=0){
                                        $this->jsonData["Data"]["Vehiculos"] = $this->getModelos();
                                    }
                                break;
                                case 'Vehiculos':
                                    $this->jsonData["Bandera"] = true;
                                    $this->jsonData["Data"]["Vehiculos"] = $this->getModelos();
                                break;
                                case 'Modelos':
                                    $this->jsonData["Bandera"] = true;
                                    $this->jsonData["Data"]["Modelos"] = $this->getAnios();
                                break;
                                case 'Anios':
                                case 'Refaccion':
                                case 'Paginacion':
                                    $this->jsonData["Bandera"] = true;
                                break;
                            }
                            $buscarlikes = $this->getexplode($this->formulario["producto"]);
                            $this->jsonData["Data"]["Trefacciones"] = $this->getTrefacciones($buscarlikes);
                            $this->jsonData["Data"]["Refacciones"] = $this->getRefacciones($buscarlikes, $this->formulario["x"],$this->formulario["y"]);
                            $this->jsonData["Data"]["BusquedaRelajada"] = $this->usarBusquedaRelajada;
                        break;
                        case 'OneRefaccion':
                            $this->jsonData["Data"]["Refaccion"] = $this->getOneRefaccion();
                            $this->jsonData["Data"]["Galeria"] = $this->getGeleria($this->formulario["id"]);
                            $this->jsonData["Data"]["Productos"] = $this->getProductos($this->jsonData["Data"]["Refaccion"]);
                            $this->jsonData["Data"]["Compatibilidad"] = $this->getCompatibilidad($this->formulario["id"]);
                            $this->jsonData["Bandera"] = true;
                        break;
                        case 'AnalizarBusqueda':
                            $this->jsonData["Data"] = $this->analizarBusquedaInteligente();
                            $this->jsonData["Bandera"] = true;
                        break;
                    }
                break;
            }
            print json_encode($this->jsonData);
        }

        private function getCompatibilidad($id){
            $array = array();
            $sql = "SELECT * FROM compatibilidad as comp 
            inner join Marcas as M on (M._id = comp.idmarca)
            inner join Modelos as V on (V._id = comp.idmodelo) where id_imagen = $id order by idcompatibilidad";
            $di = $this->conn->query($sql);
            while($row= $this->conn->fetch($di)){
                array_push($array, $row);
            }
            return $array;
        }
        
        private function getCategorias(){
            $cacheKey = $this->buildCacheKey(['categoria', 'marca', 'vehiculo', 'proveedor', 'disponibilidad', 'producto']);
            $cache = $this->getCache('cache_categorias', $cacheKey);
            if ($cache !== null) return $cache;

            $condicion = $this->buildCondicionesSQL(['categoria']);

            $sql = "
                SELECT 
                    P._idCategoria AS _id,
                    cate.Categoria,
                    COUNT(P._id) AS cantidad_repetida
                FROM u619477378_macromau.Producto P
                INNER JOIN u619477378_macromau.Categorias cate ON cate._id = P._idCategoria
                LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                INNER JOIN u619477378_macromau.Marcas M ON P._idMarca = M._id
                INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                WHERE 
                    cate.Status = 1
                    AND P.Estatus = 1 AND P.Publicar = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND MO.Estatus = 1
                    $condicion
                GROUP BY P._idCategoria, cate.Categoria
                HAVING COUNT(P._id) > 0
                ORDER BY cantidad_repetida DESC
            ";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));

            $tags = [];
            if (!empty($this->formulario['marca'])) { foreach (explode(',', $this->formulario['marca']) as $idMarca) { $tags[] = "marca:$idMarca"; } }
            if (!empty($this->formulario['vehiculo'])) { foreach (explode(',', $this->formulario['vehiculo']) as $idModelo) { $tags[] = "modelo:$idModelo"; } }

            $this->setCacheWithTags('cache_categorias', $cacheKey, $resultado, $tags);
            return $resultado;
        }

        private function getMarcas(){
            $cacheKey = $this->buildCacheKey(['categoria', 'marca', 'vehiculo', 'proveedor', 'disponibilidad', 'producto']);
            $cache = $this->getCache('cache_marcas', $cacheKey);
            if ($cache !== null) return $cache;

            $condicion = $this->buildCondicionesSQL(['marca', 'vehiculo']);

            $sql = "
                SELECT 
                    M._id AS _idMarca,
                    M.Marca,
                    COUNT(P._id) AS cantidad_repetida
                FROM u619477378_macromau.Producto P
                INNER JOIN u619477378_macromau.Marcas M ON M._id = P._idMarca
                LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                WHERE
                    M.Estatus = 1
                    AND P.Estatus = 1 AND P.Publicar = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND C.Status = 1 AND MO.Estatus = 1
                    $condicion
                GROUP BY M._id, M.Marca
                HAVING COUNT(P._id) > 0
                ORDER BY cantidad_repetida DESC
            ";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));

            $tags = [];
            if (!empty($this->formulario['categoria'])) { foreach (explode(',', $this->formulario['categoria']) as $idCategoria) { $tags[] = "categoria:$idCategoria"; } }
            if (!empty($this->formulario['proveedor'])) { foreach (explode(',', $this->formulario['proveedor']) as $idProveedor) { $tags[] = "proveedor:$idProveedor"; } }
            if (!empty($this->formulario['vehiculo'])) { foreach (explode(',', $this->formulario['vehiculo']) as $idModelo) { $tags[] = "modelo:$idModelo"; } }

            $this->setCacheWithTags('cache_marcas', $cacheKey, $resultado, $tags);
            return $resultado;
        }

        private function getExistencias(){
            $condicion = "";
            $cacheKey = $this->buildCacheKey(['categoria','marca','vehiculo','proveedor','disponibilidad','producto']);
            $cache = $this->getCache('cache_existencias', $cacheKey);
            if ($cache !== null) return $cache;

            $condicion = $this->buildCondicionesSQL(['disponibilidad']);
            $whereBusqueda = $this->buildWhereBusquedaSQL();   
                                 
            $sql = "SELECT COUNT(*) as cantidad_repetida 
                    FROM u619477378_macromau.Producto P 
                    LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                    INNER JOIN u619477378_macromau.Marcas M ON P._idMarca = M._id
                    INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                    INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                    WHERE P.Estatus = 1 AND P.Publicar = 1 AND P.stock > 0 
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
                    $whereBusqueda $condicion";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));

            $tags = [];
            if (!empty($this->formulario['marca'])) { foreach (explode(',', $this->formulario['marca']) as $idMarca) { $tags[] = "marca:$idMarca"; } }
            if (!empty($this->formulario['vehiculo'])) { foreach (explode(',', $this->formulario['vehiculo']) as $idModelo) { $tags[] = "modelo:$idModelo"; } }

            $this->setCacheWithTags('cache_existencias', $cacheKey, $resultado, $tags);
            return $resultado;
        }

        private function getOfertas(){     
            $cacheKey = 'ofertas_global';
            $cache = $this->getCache('cache_ofertas', $cacheKey);
            if ($cache !== null) return $cache;

            $sql = "SELECT COUNT(*) as cantidad_repetida 
                    FROM u619477378_macromau.Producto P
                    LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                    INNER JOIN u619477378_macromau.Marcas M ON P._idMarca = M._id
                    INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                    INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                    WHERE P.Estatus = 1 AND P.Publicar = 1 AND P.RefaccionOferta = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));
            $this->setCacheWithTags('cache_ofertas', $cacheKey, $resultado, ['ofertas']);
            return $resultado;
        }

        private function getNuevos(){
            $cacheKey = $this->buildCacheKey(['categoria', 'marca', 'vehiculo', 'proveedor', 'disponibilidad']);
            $cache = $this->getCache('cache_nuevos', $cacheKey);
            if ($cache !== null) return $cache;

            $condicion = $this->buildCondicionesSQL(['disponibilidad']);
            $sql = "
                SELECT COUNT(*) AS cantidad_repetida
                FROM u619477378_macromau.Producto P
                LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                INNER JOIN u619477378_macromau.Marcas M ON P._idMarca = M._id
                INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                WHERE
                    P.Estatus = 1 AND P.Publicar = 1 AND P.RefaccionNueva = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
                    $condicion
            ";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));
            $tags = ['nuevos'];
            if (!empty($this->formulario['marca'])) { foreach (explode(',', $this->formulario['marca']) as $idMarca) { $tags[] = "marca:$idMarca"; } }
            if (!empty($this->formulario['vehiculo'])) { foreach (explode(',', $this->formulario['vehiculo']) as $idModelo) { $tags[] = "modelo:$idModelo"; } }
            if (!empty($this->formulario['categoria'])) { foreach (explode(',', $this->formulario['categoria']) as $idCategoria) { $tags[] = "categoria:$idCategoria"; } }

            $this->setCacheWithTags('cache_nuevos', $cacheKey, $resultado, $tags);
            return $resultado;
        }

        private function getModelos(){
            if (empty($this->formulario['marca'])) return [];

            $cacheKey = $this->buildCacheKey(['marca', 'categoria', 'proveedor', 'disponibilidad', 'producto']);
            $cache = $this->getCache('cache_modelos', $cacheKey);
            if ($cache !== null) return $cache;

            $condicion = $this->buildCondicionesSQL(['vehiculo']);

            $sql = "
                SELECT 
                    M._id,
                    M.Modelo,
                    P._idMarca,
                    COUNT(P._id) AS cantidad_repetida
                FROM u619477378_macromau.Modelos M
                INNER JOIN u619477378_macromau.Producto P ON P.Modelo = M._id
                LEFT JOIN u619477378_macromau.Proveedor PROV ON P.id_proveedor = PROV._id
                INNER JOIN u619477378_macromau.Marcas MA ON P._idMarca = MA._id
                INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                WHERE
                    M.Estatus = 1 AND P.Estatus = 1 AND P.Publicar = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND MA.Estatus = 1 AND C.Status = 1
                    $condicion
                GROUP BY M._id, M.Modelo, P._idMarca
                HAVING COUNT(P._id) > 0
                ORDER BY cantidad_repetida DESC
            ";

            $resultado = $this->conn->fetch_all($this->conn->query($sql));

            $tags = [];
            foreach (explode(',', $this->formulario['marca']) as $idMarca) { $tags[] = "marca:$idMarca"; }
            if (!empty($this->formulario['categoria'])) { foreach (explode(',', $this->formulario['categoria']) as $idCategoria) { $tags[] = "categoria:$idCategoria"; } }

            $this->setCacheWithTags('cache_modelos', $cacheKey, $resultado, $tags);
            return $resultado;
        }

        private function getProveedores(){
            $condicion = $this->buildCondicionesSQL(['proveedor']);
            $whereBusqueda = $this->buildWhereBusquedaSQL();

            $sql = "SELECT 
                        P.id_proveedor,
                        prove.Proveedor,
                        P.Estatus,
                        COUNT(*) as cantidad_repetida
                    FROM u619477378_macromau.Producto P
                    INNER JOIN u619477378_macromau.Proveedor prove ON P.id_proveedor = prove._id 
                    INNER JOIN u619477378_macromau.Marcas M ON P._idMarca = M._id
                    INNER JOIN u619477378_macromau.Categorias C ON P._idCategoria = C._id
                    INNER JOIN u619477378_macromau.Modelos MO ON P.Modelo = MO._id
                    WHERE 
                        prove.Estatus = 1 
                        AND P.Estatus = 1 AND P.Publicar = 1 
                        AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
                        $whereBusqueda 
                        $condicion
                    GROUP BY P.id_proveedor
                    HAVING COUNT(*) > 0
                    ORDER BY cantidad_repetida DESC";

            return $this->conn->fetch_all($this->conn->query($sql));
        }

        private function getAnios(){
            if(str_contains($this->formulario["vehiculo"],",")){
                $sql = "Select _id, Anio from Anios where _idModelo IN(".$this->formulario["vehiculo"]. ") order by Anio asc";
            } else{
                $sql = "Select _id, Anio from Anios where _idModelo= ".$this->formulario["vehiculo"]. " order by Anio asc";
            }
            return $this->conn->fetch_all($this->conn->query($sql));
        }

        private function getexplode($string){
            $arrayLikes = array("Productos" => "(", "Clave" => "(", "No_parte" => "(");
            $array = preg_split('/\s+/', trim($string));
            $limitarray = count($array);

            foreach ($array as $key => $value) {
                if (preg_match('/^\d+$/', $value)) {
                    $likeProducto = "P.Producto LIKE '%$value%'";
                    $likeClave    = "P.Clave = $value";
                    $likeNoParte  = "P.No_parte LIKE '%$value%'";
                }
                else if (preg_match('/^[A-Za-z0-9\-]+$/', $value)) {
                    $likeProducto = "P.Producto LIKE '%$value%'";
                    $likeClave    = "P.Clave LIKE '%$value%'";
                    $likeNoParte  = "P.No_parte LIKE '%$value%'";
                }
                else {
                    $likeProducto = "P.Producto LIKE '%$value%'";
                    $likeClave    = "P.Clave LIKE '%$value%'";
                    $likeNoParte  = "P.No_parte LIKE '%$value%'";
                }

                if ($key < ($limitarray - 1)) {
                    $arrayLikes["Productos"] .= "$likeProducto AND ";
                    $arrayLikes["Clave"]     .= "$likeClave AND ";
                    $arrayLikes["No_parte"]  .= "$likeNoParte AND ";
                } else {
                    $arrayLikes["Productos"] .= "$likeProducto)";
                    $arrayLikes["Clave"]     .= "$likeClave)";
                    $arrayLikes["No_parte"]  .= "$likeNoParte)";
                }
            }
            return $arrayLikes;
        }

        private function buildWhereBusqueda(array $arrayLikes) {
            $busqueda = trim($this->formulario["producto"] ?? "");
            $usarFulltext = false;
            $busquedaFulltext = "";

            if (strlen($busqueda) >= 6 && preg_match('/[a-zA-Z]{3,}/', $busqueda)) {
                $usarFulltext = true;
                
                if ($this->usarBusquedaRelajada) {
                    $busquedaFulltext = $busqueda; 
                } else {
                    $palabras = preg_split('/\s+/', $busqueda);
                    $palabras_estrictas = array_map(function($p) { return '+' . $p; }, $palabras);
                    $busquedaFulltext = implode(' ', $palabras_estrictas);
                }
            }

            $where = "(({$arrayLikes['Productos']} OR {$arrayLikes['Clave']} OR {$arrayLikes['No_parte']})
            " . ($usarFulltext ? " OR (MATCH(P.Producto, P.Descripcion) AGAINST ('$busquedaFulltext' IN BOOLEAN MODE))": "") . ")";

            return [
                'where'            => $where,
                'usarFulltext'     => $usarFulltext,
                'busqueda'         => $busqueda,
                'busquedaFulltext' => $busquedaFulltext
            ];
        }

        private function buildWhereBusquedaSQL() {
            if (empty($this->formulario["producto"])) return "";
            $arrayLikes = $this->getexplode($this->formulario["producto"]);
            $whereData  = $this->buildWhereBusqueda($arrayLikes);
            return " AND {$whereData['where']} ";
        }

        private function getTrefacciones($arrayLikes){
            $cacheKey = $this->buildCacheKey(['producto','categoria','marca','vehiculo','proveedor','disponibilidad']) . ($this->usarBusquedaRelajada ? '_relaxed' : '_strict');
            
            $cache = $this->getCache('cache_trefacciones', $cacheKey);
            if ($cache !== null && ($cache > 0 || $this->usarBusquedaRelajada)) {
                return $cache;
            }
            
            $whereData = $this->buildWhereBusqueda($arrayLikes);
            $condicion = $this->buildCondicionesSQL();

            $sql = "
                SELECT COUNT(*) AS Trefacciones
                FROM Producto AS P
                LEFT JOIN Proveedor AS PROV ON P.id_proveedor = PROV._id
                INNER JOIN Marcas AS M ON P._idMarca = M._id
                INNER JOIN Categorias AS C ON P._idCategoria = C._id
                INNER JOIN Modelos AS MO ON P.Modelo = MO._id
                WHERE
                    P.Estatus = 1 AND P.Publicar = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
                    AND {$whereData['where']}
                    $condicion
            ";

            $row = $this->conn->fetch($this->conn->query($sql));
            $total = $row["Trefacciones"];

            if ($total == 0 && !$this->usarBusquedaRelajada && !empty($whereData['busqueda'])) {
                $this->usarBusquedaRelajada = true;
                return $this->getTrefacciones($arrayLikes); 
            }

            $this->setCache('cache_trefacciones', $cacheKey, $total);
            return $total;
        }

        private function getRefacciones($arrayLikes, $x=0, $y = 21 ){
            $array = array();
            $orden = $this->formulario["orden"];
            $tipodeorden = $this->formulario["tipodeorden"];

            $whereData = $this->buildWhereBusqueda($arrayLikes);
            $busqueda = $whereData['busqueda'];
            $busquedaFulltext = $whereData['busquedaFulltext'];
            
            $cacheKey = $this->buildCacheKey(['producto','categoria','marca','vehiculo','proveedor','disponibilidad','orden','tipodeorden','x','y']) . ($this->usarBusquedaRelajada ? '_relaxed' : '_strict');
            $cache = $this->getCache('cache_refacciones', $cacheKey);
            if ($cache !== null) return $cache;
            
            $usarFulltext = $whereData['usarFulltext'];
            
            $ordenPrioridad = "";
            if (preg_match('/^\d+$/', $busqueda)) {
                $ordenPrioridad = "
                    CASE
                        WHEN P.Clave = $busqueda THEN 100
                        WHEN P.No_parte LIKE '%$busqueda%' THEN 60
                        WHEN P.Producto LIKE '%$busqueda%' THEN 40
                        " . ($usarFulltext ? " WHEN MATCH(P.Producto, P.Descripcion) AGAINST ('$busquedaFulltext' IN BOOLEAN MODE) THEN 30 " : "") . "
                        ELSE 0
                    END DESC,
                ";
            } else {
                $ordenPrioridad = "
                    CASE
                        WHEN P.No_parte = '$busqueda' THEN 80
                        WHEN P.No_parte LIKE '%$busqueda%' THEN 60
                        WHEN P.Producto LIKE '%$busqueda%' THEN 40
                        " . ($usarFulltext ? " WHEN MATCH(P.Producto, P.Descripcion) AGAINST ('$busquedaFulltext' IN BOOLEAN MODE) THEN 30 " : "") . "
                        ELSE 0
                    END DESC,
                ";
            }

            $condicion = $this->buildCondicionesSQL();
                            
            $sql = "SELECT P.*, PROV._id as idProveedor, PROV.Proveedor as NombreProveedor, PROV.tag_alt as tag_altproveedor, PROV.tag_title as tag_titleproveedor 
            FROM Producto AS P 
            LEFT JOIN Proveedor AS PROV ON P.id_proveedor = PROV._id
            INNER JOIN Marcas AS M ON P._idMarca = M._id
            INNER JOIN Categorias AS C ON P._idCategoria = C._id
            INNER JOIN Modelos AS MO ON P.Modelo = MO._id
            WHERE P.Estatus = 1 AND P.Publicar = 1 
            AND (PROV._id IS NULL OR PROV.Estatus = 1) 
            AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
            AND {$whereData['where']} $condicion 
            ORDER BY $ordenPrioridad P.$orden $tipodeorden LIMIT $x, $y";

            $id = $this->conn->query($sql);
            while ($row = $this->conn->fetch($id)){
                $row["imagen"] = file_exists("../../../images/refacciones/{$row["_id"]}.png");
                $row["imagenproveedor"] = $row["idProveedor"]!= null? file_exists("../../../images/Marcasrefacciones/{$row["idProveedor"]}.png"):false;
                $row["Enviogratis"] = $row["Enviogratis"] == 1? true: false;
                $row["RefaccionOferta"] = $row["RefaccionOferta"] == 1? true: false;
                array_push($array, $row);
            }
            $productTags = [];
            foreach ($array as $row) { if (!empty($row['_id'])) { $productTags[] = "producto:{$row['_id']}"; } }
            $tags = $productTags;

            if (!empty($this->formulario['marca'])) { foreach (explode(',', $this->formulario['marca']) as $idMarca) { $tags[] = "marca:$idMarca"; } }
            if (!empty($this->formulario['vehiculo'])) { foreach (explode(',', $this->formulario['vehiculo']) as $idModelo) { $tags[] = "modelo:$idModelo"; } }
            
            $this->setCacheWithTags('cache_refacciones', $cacheKey, $array, $tags);
            return $array;
        }

        private function getOneRefaccion(){
            $sql = "select P._id, P.Clave, P.Producto, C.Categoria, M.Marca, P.Precio1, P.Precio2,
                P.No_parte, P.Descripcion, V.Modelo, A.Anio, P.RefaccionNueva, P.RefaccionOferta,
                P.Alto, P.Ancho, P.Largo, P.Peso, P._idCategoria, P._idMarca, P.Anios, P.Modelo as _idModelo, P.id_proveedor,
                P.Enviogratis, PR.Proveedor, P.stock, P.Publicar, P.Kit
                from Producto as P 
                inner join Categorias as C on (C._id = P._idCategoria)
                inner join Marcas as M on (M._id = P._idMarca)
                inner join Modelos as V on (V._id = P.Modelo)
                inner join Anios as A on (A._id = P.Anios)
                left join Proveedor as PR on (PR._id = P.id_proveedor)
                where P._id = {$this->formulario["id"]}
                AND C.Status = 1 AND M.Estatus = 1 AND V.Estatus = 1 AND (PR._id IS NULL OR PR.Estatus = 1)";
                
            $row = $this->conn->fetch($this->conn->query($sql));
            $tags = ["producto:{$row['_id']}", "marca:{$row['_idMarca']}", "modelo:{$row['_idModelo']}"];
            $this->setCacheWithTags('cache_producto', "producto:{$row['_id']}", $row, $tags);
            $row["imagen"] = file_exists("../../../images/refacciones/{$row["_id"]}.png");
            $row["Enviogratis"] = $row["Enviogratis"]==1? true: false;
            $row["RefaccionOferta"] = $row["RefaccionOferta"] == 1? true: false;
            return $row;
        }

        private function getGeleria ($id){
            $array = array();
            $sql = "SELECT _id, tag_alt, tag_title FROM galeriarefacciones where id_producto = $id";
            return $this->conn->fetch_all($this->conn->query($sql));
        }

        private function getProductos($data){
            $array = [];

            $sqlRand = "
                SELECT _id FROM Producto WHERE 
                    Modelo = {$data['_idModelo']} AND _idMarca = {$data['_idMarca']} AND Anios = {$data['Anios']} AND Estatus = 1
                ORDER BY _id DESC LIMIT 1
            ";
            $rowMax = $this->conn->fetch($this->conn->query($sqlRand));
            $randId = $rowMax ? rand(1, (int)$rowMax['_id']) : 1;

            $sql = "
                SELECT 
                    P.*, 
                    PROV._id AS idProveedor
                FROM Producto P
                LEFT JOIN Proveedor PROV ON P.id_proveedor = PROV._id
                INNER JOIN Marcas M ON P._idMarca = M._id
                INNER JOIN Categorias C ON P._idCategoria = C._id
                INNER JOIN Modelos MO ON P.Modelo = MO._id
                WHERE 
                    P.Modelo = {$data['_idModelo']}
                    AND P._idMarca = {$data['_idMarca']}
                    AND P.Anios = {$data['Anios']}
                    AND P.Estatus = 1
                    AND (PROV._id IS NULL OR PROV.Estatus = 1) 
                    AND M.Estatus = 1 AND C.Status = 1 AND MO.Estatus = 1
                    AND P._id >= {$randId}
                LIMIT 21
            ";

            $id = $this->conn->query($sql);

            while ($row = $this->conn->fetch($id)) {
                $row["imagen"] = file_exists("../../../images/refacciones/{$row["_id"]}.png");
                $row["imagenproveedor"] = $row["idProveedor"] != null ? file_exists("../../../images/Marcasrefacciones/{$row["idProveedor"]}.png") : false;
                $row["Enviogratis"] = $row["Enviogratis"] == 1;
                $array[] = $row;
            }
            $productTags = [];
            foreach ($array as $row) { if (!empty($row['_id'])) { $productTags[] = "producto:{$row['_id']}"; } }
            $tags = array_merge($productTags, ["marca:{$data['_idMarca']}", "modelo:{$data['_idModelo']}"]);
            return $array;
        }

    }
    
$app = new Catalogo($array_principal);
$app->principal();