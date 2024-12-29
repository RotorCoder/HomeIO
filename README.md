HomeIO - PHP smart home mobile-friendly webapp (2024 Christmas vacation project)

Controls Govee and Philips Hue devices (Govee's public API has very restrictive rate limiting. Switching to Hue due to the Hue bridge local API)

Listens for x10 remote RF input through x10 Commander log (Using CM15A on Windows PC. Might be discontinued/hard to find software.)

Reads Govee H5075 Thermometers and displays on assigned room (Script reads data directly from BLE advertisements. I also run on the Windows PC due to no Hyper-v host bluetooth/USB access)

-Repository files sit in /var/www/html/homeio

-Shared folder is copied from /var/www/html/shared

<div align="center">
    <img src="/../master/assets/images/HomeIO-mobile3.png" width="300px"</img> 
</div>
<div align="center">
    <img src="/../master/assets/images/HomeIO-full3.png" width="800px"</img> 
</div>
