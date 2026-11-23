# Analizador Cobertura Meshtastic

Página web para medir la cobertura real entre nodos [Meshtastic](https://meshtastic.org). Un nodo se queda fijo enviando su posición y otro va contigo por la ruta, conectado al móvil por Bluetooth. La página apunta cada paquete recibido junto con la posición GPS y la calidad de la señal (SNR/RSSI), y lo dibuja en un mapa.

👉 **Úsala aquí:** https://meshtastic.flydecision.com

## Qué hace

- Se conecta al nodo por **Web Bluetooth** (Chrome en Android o en PC), sin instalar nada.
- Registra los paquetes de radio de otros nodos (posición, telemetría, range test) con GPS, SNR, RSSI y saltos.
- Mapa con puntos coloreados por SNR, recorrido GPS y vista en **cuadrícula** con el porcentaje de recepción por zona.
- Guarda y abre los datos en **CSV** y el recorrido en **GPX**. Se pueden sumar varios archivos.
- Capas de mapa topográfico, satélite, OpenTopoMap, OpenStreetMap y relieve.

## Cómo usarla

1. Abre la página en Chrome (tiene que ser por `https`).
2. Pulsa **ᛒ Conectar** y elige tu nodo.
3. Sal de ruta. Al volver, pulsa **💾 Guardar**.

La ayuda completa está dentro de la propia página (botón de ayuda).

## Recomendaciones de radio

- Los dos nodos con los mismos ajustes LoRa (BW, SF, CR y slot).
- El nodo fijo con posición fija e intervalo de 30–60 s.
- El nodo de ruta en `CLIENT_MUTE` y sin ahorro de energía, para que siempre escuche.
- No uses firmware 2.8 o posterior si necesitas el módulo Range Test: ya no viene incluido. La página funciona igual con paquetes de posición y telemetría.

## Desarrollo

```
src/index.html   la web completa (HTML, CSS y JavaScript en un solo archivo)
```

Usa [Leaflet](https://leafletjs.com) desde CDN. Para probar cambios basta con abrir `src/index.html` en Chrome; para el Bluetooth y el GPS hace falta servirlo por `https` o `localhost`.

La carpeta `src/` es la que se publica en la web y la que usará la futura app Android.

## Licencia

[GNU AGPL v3](LICENSE), la misma que [FlyDecision](https://github.com/flydecision/flydecision).
