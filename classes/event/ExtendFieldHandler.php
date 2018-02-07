<?php namespace Lovata\OmnipayShopaholic\Classes\Event;

use Cms\Classes\Page;
use Omnipay\Omnipay;
use Lovata\OrdersShopaholic\Models\Status;
use Lovata\OrdersShopaholic\Models\PaymentMethod;
use Lovata\OrdersShopaholic\Controllers\PaymentMethods;

/**
 * Class ExtendFieldHandler
 * @package Lovata\OmnipayShopaholic\Classes\Event
 * @author Andrey Kharanenka, a.khoronenko@lovata.com, LOVATA Group
 */
class ExtendFieldHandler
{
    /**
     * Add listeners
     * @param \Illuminate\Events\Dispatcher $obEvent
     */
    public function subscribe($obEvent)
    {
        $obEvent->listen('backend.form.extendFields', function ($obWidget) {
            $this->extendPaymentMethodFields($obWidget);
        });
    }

    protected function getGatewayNamespaces()
    {
        $file = base_path() . '/vendor/composer/autoload_psr4.php';
        if (is_readable($file)) {
            $namespaces = include $file;
            return array_keys($namespaces);
        }
        return array();
    }

    /**
     * Returns an array of gateway IDs (short names) extracted from the registered namespaces
     * @return array
     */
    public function getGatewayIds()
    {
        $gateways = array();
        
        foreach ($this->getGatewayNamespaces() as $namespace) {
            if (strpos($namespace, 'Omnipay') !== 0) {
                continue;
            }
            $matches = array();
            preg_match('/Omnipay\\\(.+?)\\\/', $namespace, $matches);

            if (isset($matches[1])) {
                $gateways[] = trim($matches[1]);
            }
        }

        return $gateways;
    }

    /**
     * Extend settings fields
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function extendPaymentMethodFields($obWidget)
    {
        // Only for the Settings controller
        if (!$obWidget->getController() instanceof PaymentMethods) {
            return;
        }

        // Only for the Settings model
        if (!$obWidget->model instanceof PaymentMethod) {
            return;
        }

        //Get payment gateway list
        $arPaymentGatewayList = [];

        foreach ($this->getGatewayIds() as $id) {
            $class = \Omnipay\Common\Helper::getGatewayClassName($id);
            if (class_exists($class)) {
                \Omnipay\Omnipay::register($id);
            }
        }

        $arGatewayList = Omnipay::getFactory()->find();

        if (!empty($arGatewayList)) {
            foreach ($arGatewayList as $sGatewayName) {
                $arPaymentGatewayList[$sGatewayName] = $sGatewayName;
            }
        }

        //Get order status list
        $arStatusList = Status::lists('name', 'id');

        // Add an extra birthday field
        $obWidget->addTabFields([
            'before_status_id' => [
                'label'       => 'lovata.omnipayshopaholic::lang.field.before_status_id',
                'tab'         => 'lovata.omnipayshopaholic::lang.tab.gateway',
                'type'        => 'dropdown',
                'span'        => 'left',
                'options'     => $arStatusList,
                'emptyOption' => 'lovata.toolbox::lang.field.empty',
            ],
            'after_status_id'  => [
                'label'       => 'lovata.omnipayshopaholic::lang.field.after_status_id',
                'tab'         => 'lovata.omnipayshopaholic::lang.tab.gateway',
                'type'        => 'dropdown',
                'span'        => 'right',
                'options'     => $arStatusList,
                'emptyOption' => 'lovata.toolbox::lang.field.empty',
            ],
            'gateway_id'       => [
                'label'       => 'lovata.omnipayshopaholic::lang.field.gateway_id',
                'tab'         => 'lovata.omnipayshopaholic::lang.tab.gateway',
                'type'        => 'dropdown',
                'span'        => 'left',
                'options'     => $arPaymentGatewayList,
                'emptyOption' => 'lovata.toolbox::lang.field.empty',
            ],
            'gateway_currency' => [
                'label' => 'lovata.omnipayshopaholic::lang.field.gateway_currency',
                'tab'   => 'lovata.omnipayshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'right',
            ],
        ]);

        $this->addGatewayPropertyFields($obWidget->model, $obWidget);
    }

    /**
     * Add gateway property list
     * @param PaymentMethod         $obPaymentMethod
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function addGatewayPropertyFields($obPaymentMethod, $obWidget)
    {
        if (empty($obPaymentMethod) || empty($obPaymentMethod->gateway_id) || empty($obWidget)) {
            return;
        }

        //Create gateway object
        $obGateway = Omnipay::create($obPaymentMethod->gateway_id);
        if (empty($obGateway)) {
            return;
        }

        //Get default property list for gateway
        $arPropertyList = $obGateway->getDefaultParameters();
        if (empty($arPropertyList)) {
            return;
        }

        //Process property list  for gateway
        foreach ($arPropertyList as $sPropertyName => $arValueList) {
            if (empty($sPropertyName)) {
                continue;
            }

            if (is_array($arValueList)) {
                $obWidget->addTabFields([
                    'gateway_property['.$sPropertyName.']' => [
                        'label'   => $sPropertyName,
                        'tab'     => 'lovata.omnipayshopaholic::lang.tab.gateway',
                        'type'    => 'dropdown',
                        'span'    => 'left',
                        'options' => $arValueList,
                    ],
                ]);
            } elseif (is_bool($arValueList)) {
                $obWidget->addTabFields([
                    'gateway_property['.$sPropertyName.']' => [
                        'label'   => $sPropertyName,
                        'tab'     => 'lovata.omnipayshopaholic::lang.tab.gateway',
                        'type'    => 'checkbox',
                        'default' => $arValueList,
                        'span'    => 'left',
                    ],
                ]);
            } else {
                $obWidget->addTabFields([
                    'gateway_property['.$sPropertyName.']' => [
                        'label' => $sPropertyName,
                        'tab'   => 'lovata.omnipayshopaholic::lang.tab.gateway',
                        'type'  => 'text',
                        'span'  => 'left',
                    ],
                ]);
            }
        }
    }
}
