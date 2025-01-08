<h2>HomeIO</h2>
<p>PHP smart home mobile-friendly webapp</p>
<p><strong>Project Goals:</strong><br />-Simple web UI without all the clutter or ads (minimal clicks or taps to use)<br />-Real time device statuses (no syncing delays)<br />-Customizable brightness steps to simplify dimming<br />-Customizable grouping and rooms<br />-Physical remotes without delays (with customizable actions)<br />-Temperature & humidity logging & display using inexpensive store bought sensors</p>
<p><strong>Inputs:</strong><br />-Web UI<br />-X10 rf remotes (RF signals logged with CM15A)<br />-Govee 1, 2, and 6 button bluetooth remotes (ble decoded using https://github.com/Bluetooth-Devices/govee-ble)<br />-Govee 5100 &amp; 5175 thermometer/hygrometers (ble decoded using https://github.com/Bluetooth-Devices/govee-ble)</p>
<p><strong>Outputs:</strong><br />-Govee devices (Govee public API)<br />-Vesync devices (API access logic comes from https://github.com/webdjoe/pyvesync)<br />-Philips Hue devices (Hue Bridge API)</p>
<p>-Repository files sit in /var/www/html/homeio<br />-Shared folder is copied from /var/www/html/shared</p>

<div align="center">
    <img src="/../master/assets/images/HomeIO-mobile4.png" width="300px"</img>
</div>
<div align="center">
    <img src="/../master/assets/images/HomeIO-full4.png" width="800px"</img>
</div>
