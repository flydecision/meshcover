# Analizador Cobertura Meshtastic

> 🧪 **Repositorio de pruebas.** Esto es un experimento personal para medir la cobertura real de una red Meshtastic. La idea es que, si funciona bien, se ofrezca a la comunidad de Meshtastic para que lo integre, lo modifique o lo rechace, como mejor le parezca. Las ideas, los fallos y las propuestas son bienvenidos.

Página web para analizar la cobertura real entre nodos [Meshtastic](https://meshtastic.org) a partir del registro que exporta la **app oficial de Android**. Un nodo se queda fijo enviando su posición y otro va contigo por la ruta, conectado a la app. La app graba en segundo plano, con la pantalla apagada; al volver, exportas el registro y esta página coloca cada paquete recibido donde estabas, con su SNR y RSSI, y lo dibuja en un mapa.

👉 **Úsala aquí:** https://meshtastic.flydecision.com

## Qué hace

- Abre el registro del panel de depuración de la app oficial (`.txt`), y también CSV y GPX de versiones anteriores. Se pueden sumar varios archivos.
- Cruza las horas de cada paquete recibido por radio con las posiciones GPS del nodo de ruta.
- Mapa con puntos coloreados por SNR, recorrido y vista en **cuadrícula** con el porcentaje de recepción por zona.
- Filtros por nodo y por saltos (mínimo y máximo). Lo que llega repetido por otro nodo se marca aparte, porque su SNR es el del último salto.
- Capas de mapa topográfico, satélite, OpenTopoMap, OpenStreetMap y relieve.
- No usa Bluetooth: funciona en cualquier navegador, en el móvil o en el PC.

## Cómo usarla

1. **Nodo fijo**: posición fija e intervalo de difusión de 1 minuto.
2. **Nodo de ruta**: rol `CLIENT_MUTE`, GPS activado e intervalo de difusión de 1 minuto. Los mismos ajustes LoRa (BW, SF, CR y slot) en los dos.
3. Conecta la app oficial al nodo de ruta, vacía el registro del panel de depuración y sal de ruta con la pantalla apagada.
4. Al volver: Ajustes → panel de depuración → exportar. Abre el `.txt` aquí con **📂 Añadir registros**, elige el nodo fijo y pulsa **🔳 Cuadrícula**.

La ayuda completa está dentro de la propia página (botón de ayuda).

## Desarrollo

```
src/index.html   la web completa (HTML, CSS y JavaScript en un solo archivo)
```

Usa [Leaflet](https://leafletjs.com) desde CDN. Para probar cambios basta con abrir `src/index.html` en el navegador.

Las versiones anteriores, que grababan en vivo por Web Bluetooth, siguen en el historial de Git.

## Licencia

[GNU AGPL v3](LICENSE), la misma que [FlyDecision](https://github.com/flydecision/flydecision).
