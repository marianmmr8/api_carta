const divCarta = document.getElementById('carta');

const url = 'http://localhost/api_carta/api/platos';

/**
 * Descarga la lista completa de platos desde la API mediante fetch.
 * Si la respuesta es correcta, convierte el JSON y llama a mostrarPlatos();
 * si falla la red o la API devuelve un error HTTP, muestra un aviso en #carta.
 */
fetch(url)
    // 1. Comprueba que la petición HTTP ha ido bien (código 200-299)
    .then(respuesta => {
        if (!respuesta.ok) {
            // Lanza un error para saltar directamente al .catch()
            throw new Error(`Error ${respuesta.status} al descargar los platos`);
        }
        // Convierte el cuerpo de la respuesta de texto JSON a un array de objetos JS
        return respuesta.json();
    })
    // 2. Recibe el array de platos ya convertido y lo pinta en pantalla
    .then(platos => {
        console.log(platos);
        mostrarPlatos(platos);
    })
    // 3. Captura cualquier fallo de red o el error lanzado en el paso 1
    .catch(error => {
        console.error(error);
        divCarta.innerHTML = '<p class="error">No se pudo cargar la carta en este momento.</p>';
    });

/**
 * Dibuja la carta en el contenedor #carta agrupando los platos por categoría
 * (entrantes, principales y postres). Para cada plato crea una fila con su nombre,
 * una línea de puntos y el precio formateado, marcando como "Agotado" los que
 * tienen disponible = false.
 *
 * @param {Array<{id: number, nombre: string, categoria: string, precio: number, disponible: boolean}>} platos
 */
function mostrarPlatos(platos) {
    // Limpia el contenedor antes de volver a pintar la carta
    divCarta.innerHTML = '';

    // Orden y títulos visibles con los que se mostrarán las secciones del menú
    const categorias = [
        { clave: 'entrante', titulo: 'Entrantes' },
        { clave: 'principal', titulo: 'Platos principales' },
        { clave: 'postre', titulo: 'Postres' },
    ];

    // Recorre cada categoría definida para crear su sección correspondiente
    categorias.forEach(({ clave, titulo }) => {
        // Filtra solo los platos que pertenecen a la categoría actual
        const platosCategoria = platos.filter(plato => plato.categoria === clave);
        // Si no hay ningún plato en esta categoría, sale de esta iteración y no crea sección vacía
        if (platosCategoria.length === 0) return;

        // Crea el bloque <section class="categoria"> con su título <h2>
        const seccion = document.createElement('section');
        seccion.className = 'categoria';

        const h2 = document.createElement('h2');
        h2.textContent = titulo;
        seccion.appendChild(h2);

        // Crea la lista <ul> donde irán los platos de esta categoría
        const lista = document.createElement('ul');
        lista.className = 'lista-platos';

        // Recorre los platos filtrados de la categoría para crear cada fila <li>
        platosCategoria.forEach(plato => {
            const item = document.createElement('li');
            // Añade la clase "agotado" si el plato no está disponible
            item.className = plato.disponible ? 'plato' : 'plato agotado';

            const nombre = document.createElement('span');
            nombre.className = 'plato-nombre';
            nombre.textContent = plato.nombre;

            // Separador visual (línea de puntos) entre el nombre y el precio
            const linea = document.createElement('span');
            linea.className = 'plato-linea';

            // Formatea el precio a 2 decimales con coma e indica si está agotado
            const precio = document.createElement('span');
            precio.className = 'plato-precio';
            precio.textContent = plato.disponible
                ? `${plato.precio.toFixed(2).replace('.', ',')} €`
                : `${plato.precio.toFixed(2).replace('.', ',')} € · Agotado`;

            item.append(nombre, linea, precio);
            lista.appendChild(item);
        });

        // Inserta la lista en la sección y la sección completa en #carta
        seccion.appendChild(lista);
        divCarta.appendChild(seccion);
    });
}



