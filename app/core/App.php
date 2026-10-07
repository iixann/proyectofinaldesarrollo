<?php

/**
 * Clase Iniciadora, de arranque (Bootstrapping) App
 * Esta clase se encarga de inicializar y dirigir las solicitudes de la aplicación.
 */
class App
{
    // Propiedades para almacenar valores predeterminados del controlador, método y parámetros.
    protected $controlador  = "app\\controllers\\" . "HomeController";
    protected $metodo      = "actionIndex";
    protected $parametros      = [];

    /* El controlador por defecto es Home */
    /* El método por defecto es Index */

    /**
     * Constructor de la clase.
     * Se encarga de procesar la URL, determinar el controlador, método y parámetros,
     * y luego llama al método correspondiente del controlador.
     */
    public function __construct()
    {
        // Obtiene la URL parseada.
        $url = $this->parseUrl();

        // Establece el controlador por defecto como HomeController.
        $controladorPorDefecto = false;

        // Comprueba si hay una parte en la URL que corresponda a un controlador.
        if (!isset($url[0])) {
            // Si no hay controlador definido en la URL, se utiliza HomeController por defecto.
            $controladorActivo = $this->controlador;
            $archivoControlador = str_replace('\\', '/', $controladorActivo) . '.php';
            $controladorPorDefecto = true;
        } else {
            // Si hay un dato en la URL en la posición del Controlador, se comprueba si no es un método del HomeController.
            $controladorActivo = ucfirst(strtolower($url[0])) . "Controller";
            $archivoControlador = APP_PATH . "controllers/" . $controladorActivo . '.php';
        }

        // Comprueba si existe el archivo del controlador en el directorio principal.
        if (file_exists($archivoControlador)) {
            // Si existe, establece ese controlador para ser instanciado.
            if ($controladorPorDefecto == false) {
                $this->controlador = APP_PATH . "controllers/" . $controladorActivo;
            }

            // Instancia el controlador.
            $claseControlador =  str_replace('/', '\\', $this->controlador);
            $this->controlador = new  $claseControlador;

            // Comprueba si hay un método especificado en la URL.
            if (isset($url[1])) {
                $nombreMetodo = "action" . ucfirst(strtolower($url[1]));

                // Si el método existe en el controlador, lo establece como el método activo.
                if (method_exists($this->controlador, $nombreMetodo)) {
                    $this->metodo = $nombreMetodo;
                    unset($url[1]); // Elimina el valor del método de los parámetros.
                } else {
                    $this->metodo = 'action404'; // Establece un método por defecto si no se encuentra.
                    unset($url[1]);
                }
            }

            unset($url[0]); // Elimina el valor del controlador de los parámetros.
            $noexisteControlador = false;
        } else {
            // Si no se encuentra el controlador en el directorio principal, busca en subdirectorio.
            $subDirectorioClase = str_replace('Controller', '', $controladorActivo);
            $archivoControlador = APP_PATH . "controllers/" . strtolower($subDirectorioClase) . '/' . $controladorActivo . '.php';

            if (file_exists($archivoControlador)) {
                // Si existe, establece ese controlador para ser instanciado.
                $this->controlador = APP_PATH . "controllers/" . strtolower($subDirectorioClase) . '/' . $controladorActivo;

                $claseControlador =  str_replace('/', '\\', $this->controlador);
                $this->controlador = new  $claseControlador;

                // Comprueba si hay un método especificado en la URL.
                if (isset($url[1])) {
                    $nombreMetodo = "action" . ucfirst(strtolower($url[1]));

                    // Si el método existe en el controlador, lo establece como el método activo.
                    if (method_exists($this->controlador, $nombreMetodo)) {
                        $this->metodo = $nombreMetodo;
                        unset($url[1]); // Elimina el valor del método de los parámetros.
                    } else {
                        $this->metodo = 'action404'; // Establece un método por defecto si no se encuentra.
                        unset($url[1]);
                    }
                }

                unset($url[0]); // Elimina el valor del controlador de los parámetros.
                $noexisteControlador = false;
            } else {
                // Si no se encuentra el controlador ni en el directorio principal ni en subdirectorio.
                // Se instancia el controlador por defecto HomeController.
                $claseControlador =  str_replace('/', '\\', $this->controlador);
                $this->controlador = new  $claseControlador;

                // Comprueba si el método especificado en la URL es un método del controlador por defecto HomeController.
                $nombreMetodo = "action" . ucfirst(strtolower($url[0]));

                if (method_exists($this->controlador, $nombreMetodo)) {
                    $this->metodo = $nombreMetodo;
                    unset($url[0]); // Elimina el valor del método de los parámetros.
                } else {
                    $this->metodo = 'action404'; // Establece un método por defecto si no se encuentra.
                    unset($url[1]);
                }

                $noexisteControlador = true;
            }
        }

        // Si la URL tiene valores restantes, los agrega a los parámetros; de lo contrario, los parámetros son nulos.
        $this->parametros = $url ? array_values($url) : $this->parametros;

        // Llama al método del controlador y pasa los parámetros al método.
        call_user_func_array([$this->controlador, $this->metodo], $this->parametros);
    }

    /**
     * Método parseUrl
     * Parsea la URL y devuelve un array con los segmentos de la URL.
     */
    public function parseUrl()
    {
        if (isset($_GET['url'])) {
            return explode("/", filter_var(rtrim($_GET["url"], "/"), FILTER_SANITIZE_URL));
        }
    
        return [];

        /*

            La función rtrim en PHP se utiliza para eliminar caracteres específicos del final (derecha) de una cadena. El nombre rtrim significa "right trim" o "recorte derecho"

            Cuando se utiliza FILTER_SANITIZE_URL, la función filter_var elimina todos los caracteres ilegales de la URL, dejando solo aquellos que son permitidos en una URL válida. Esto incluye la eliminación de caracteres que no son válidos en la sintaxis de una URL, como espacios en blanco y caracteres especiales.
        */
    }
}