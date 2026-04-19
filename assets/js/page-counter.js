//import $ from 'jquery';
import Odometer from 'odometer';
import 'odometer/themes/odometer-theme-default.css';


export function odometerUpdate(num, targetSelector)
{
    let targetNode = $(targetSelector);
    if( num === null || targetNode.length == 0 ) {
        return false;
    }

    let odometer = new Odometer({
        el:  targetNode[0],
        value: targetNode.data('value'),
        format: '(.ddd)',
        theme: 'default'
    });

    odometer.render();
    odometer.update(num);
}
