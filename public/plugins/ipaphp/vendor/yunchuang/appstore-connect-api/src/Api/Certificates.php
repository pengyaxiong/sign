<?php

namespace MingYuanYun\AppStore\Api;


class Certificates extends AbstractApi
{
    public function all(array $params = [])
    {
        return $this->get('/certificates', $params);
    }
    
    public function del($id){
        return $this->delete('/certificates/'.$id);
    }
    
    public function reg(){
        $data = [
            'data' => [
                'type' => 'certificates',
                'attributes' => [
                    'certificateType' => 'IOS_DISTRIBUTION',
                    'csrContent'      => 'MIICeTCCAWECAQAwNDEyMDAGCSqGSIb3DQEJARYjNTE1MTI0NjUxQHFxLmNvbSwgQ049Sm9obiBEb2UsIEM9VVMwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDLxvubXWqKL4JHHKzr0AYiJ82Ly2zYvC3mvd1KTn71btEPFF4jN3I2FJreE+Fl4Gp/IdkmLx0mfI5S+VeeYHBn5Pty7W+ZcDHERxh1uSnd+eZi2MK6nr9jgdZXQmtFWTObhXTch+BKvmOtVNzHskFePCz0UFKfThtD/+aM9Q9U7aYrVVtM+jf2vN2038flUf3WgNs1XfnSevYsrRjvPe3l45QXlu9w2KCyUEc81g7BmPz4pBMRbJ3EqvheRtnEusumjP3rh5X/OqQrjEe5bFLepPUCmsHNfTkT8eQq1nE/urrBvw4tERdul8wvWJFK+NyTmx9GfCPn9tzMTVfr1rYbAgMBAAGgADANBgkqhkiG9w0BAQsFAAOCAQEAPhxm2SPETijc6tCxZN5k+rzP4k35xCvLXFQNWvIyjhR3VPqp/ddWv1/Xu7Uw9TsTtbp09YAP3x5mQLXEc6ltNVHGvCIWlpu8Y+Pxm5aEAhAvvO2eBygXYq9h/bv9vNj8J5s4JRew5f0TklwvHI8HADTlWAQ4RDP90zr902LdTa/nqoProkcEUW2xZ5WlKFtBQW6OOIOZxKHWk2ujCLcgO13DFKm47qOiwej5oEU4PSTb5pmTI972Qs1bmW4KYolJmhMtFKAsAlhSxnjEuHYtOvsOBgCkSWMwxmFueAzdOTHagRlVxK03Fj3gn7DTXJhmXIJnDJP2VborFjTsOOTOJg==',
                ]
            ]
        ];

        return $this->postJson('/certificates', $data);
    }
}