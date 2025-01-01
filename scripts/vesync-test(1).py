import sys
import logging
import json
from pyvesync import VeSync

logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG)

USERNAME = "steve304635@gmail.com"
PASSWORD = "Pandora2!"

def test_device():
    # Instantiate VeSync class and login
    manager = VeSync(USERNAME, PASSWORD, debug=True)
    if manager.login() == False:
        logger.debug("Unable to login")
        return

    # Pull and update devices
    manager.update()

    fan = None
    logger.debug(str(manager.fans))

    for dev in manager.fans:
        # Print all device info
        logger.debug(dev.device_name + "\n")
        logger.debug(dev.display())

        # Find correct device
        if dev.device_name.lower() == DEVICE_NAME.lower():
            fan = dev
            break

    if fan == None:
        logger.debug("Device not found")
        logger.debug("Devices found - \n %s", str(manager._dev_list))
        return


    logger.debug('--------------%s-----------------' % fan.device_name)
    logger.debug(dev.display())
    logger.debug(dev.displayJSON())
    # Test all device methods and functionality
    # Test Properties
    logger.debug("Fan is on - %s", fan.is_on)
    logger.debug("Modes - %s", fan.modes)
    logger.debug("Fan Level - %s", fan.fan_level)
    logger.debug("Fan Air Quality - %s", fan.air_quality)
    logger.debug("Screen Status - %s", fan.screen_status)

    fan.turn_on()
    fan.turn_off()
    fan.sleep_mode()
    fan.auto_mode()
    fan.manual_mode()
    fan.change_fan_speed(3)
    fan.change_fan_speed(2)
    fan.child_lock_on()
    fan.child_lock_off()
    fan.turn_off_display()
    fan.turn_on_display()

    fan.set_light_detection_on()
    logger.debug(fan.light_detection_state)
    logger.debug(fan.light_detection)

    # Only on Vital 200S
    fan.pet_mode()

    logger.debug("Set Fan Speed - %s", fan.set_fan_speed)
    logger.debug("Current Fan Level - %s", fan.fan_level)
    logger.debug("Current mode - %s", fan.mode)

    # Display all device info
    logger.debug(dev.display())
    logger.debug(dev.displayJSON())

if __name__ == "__main__":
    logger.debug("Testing device")
    test_device()
...
