<?php

use AmoCRM\Filters\Interfaces\HasOrderInterface;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\Factories\UnsortedModelFactory;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\Unsorted\FormsUnsortedCollection;
use AmoCRM\Collections\Leads\Unsorted\SipUnsortedCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\UnsortedFilter;
use AmoCRM\Filters\UnsortedSummaryFilter;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\Unsorted\BaseUnsortedModel;
use AmoCRM\Models\Unsorted\FormsMetadata;
use AmoCRM\Models\Unsorted\FormUnsortedModel;
use AmoCRM\Models\Unsorted\SipMetadata;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Ramsey\Uuid\Uuid;

include_once __DIR__ . '/boot.php';


$url = $_POST['url'];
$name = $_POST['name'];
$title = $_POST['title'];
$mail = $_POST['mail'];
$phone = $_POST['phone'];
$text = $_POST['text'];
$formid = $_POST['formid'];
$price = $_POST['price'];


$accessToken = getToken();
$apiClient->setAccessToken($accessToken)
    ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
    ->onAccessTokenRefresh(
        function (AccessTokenInterface $accessToken, string $baseDomain) {
            saveToken(
                [
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $baseDomain,
                ]
            );
        }
    );

//Добавим неразобранное форму
$formsUnsortedCollection = new FormsUnsortedCollection();
$formUnsorted = new FormUnsortedModel();
$formMetadata = new FormsMetadata();
$formMetadata
    ->setFormId('635818')
    ->setFormName('Заявка с сайта ads-vent.ru')
    ->setFormPage($url)
    ->setFormSentAt(time())
    ->setReferer($url)
    ->setIp($_SERVER['REMOTE_ADDR']);

$unsortedLead = new LeadModel();
$unsortedLead->setName($title)
    ->setPrice(0);

// $unsortedLead->setTags((new TagsCollection())
//         ->add(
//             (new TagModel())
//                 ->setName($formid)
//         )
//     );
$leadCustomFieldsValues = new CustomFieldsValuesCollection();
    $comment = new TextCustomFieldValuesModel();
    $comment->setFieldId(454443);
    $comment->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($text))
    );

    $utm_source = new TextCustomFieldValuesModel();
    $utm_source->setFieldId(454395);
    $utm_source->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($_POST['utm_source']))
    );
    $utm_medium = new TextCustomFieldValuesModel();
    $utm_medium->setFieldId(454397);
    $utm_medium->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($_POST['utm_medium']))
    );
    $utm_campaign = new TextCustomFieldValuesModel();
    $utm_campaign->setFieldId(454399);
    $utm_campaign->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($_POST['utm_campaign']))
    );
    $utm_content = new TextCustomFieldValuesModel();
    $utm_content->setFieldId(454401);
    $utm_content->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($_POST['utm_content']))
    );
    $utm_term = new TextCustomFieldValuesModel();
    $utm_term->setFieldId(454403);
    $utm_term->setValues(
        (new TextCustomFieldValueCollection())
            ->add((new TextCustomFieldValueModel())->setValue($_POST['utm_term']))
    );

    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($comment));
    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($utm_source));
    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($utm_medium));
    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($utm_campaign));
    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($utm_content));
    $unsortedLead->setCustomFieldsValues($leadCustomFieldsValues->add($utm_term));

$unsortedContactsCollection = new ContactsCollection();
$unsortedContact = new ContactModel();
$unsortedContact->setName($name);
$contactCustomFields = new CustomFieldsValuesCollection();
$phoneFieldValueModel = new MultitextCustomFieldValuesModel();
$phoneFieldValueModel->setFieldId(404191);
$phoneFieldValueModel->setValues(
    (new MultitextCustomFieldValueCollection())
        ->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($phone))
);
$emailFieldValueModel = new MultitextCustomFieldValuesModel();
$emailFieldValueModel->setFieldId(404193);
$emailFieldValueModel->setValues(
    (new MultitextCustomFieldValueCollection())
        ->add((new MultitextCustomFieldValueModel())->setEnum('WORK')->setValue($mail))
);

$unsortedContact->setCustomFieldsValues($contactCustomFields->add($phoneFieldValueModel));
$unsortedContact->setCustomFieldsValues($contactCustomFields->add($emailFieldValueModel));
$unsortedContactsCollection->add($unsortedContact);

$formUnsorted
    ->setSourceName($url)
    ->setSourceUid('my_custom_uid')
    ->setCreatedAt(time())
    ->setMetadata($formMetadata)
    ->setLead($unsortedLead)
    ->setPipelineId(3355135)
    ->setContacts($unsortedContactsCollection);

$formsUnsortedCollection->add($formUnsorted);

$unsortedService = $apiClient->unsorted();
try {
    $formsUnsortedCollection = $unsortedService->add($formsUnsortedCollection);

} catch (AmoCRMApiException $e) {
    printError($e);
    return $e;
    die;
}
$formUnsorted = $formsUnsortedCollection->first();

try {
    $unsortedFiler = new UnsortedFilter();
    $unsortedFiler
        ->setCategory([BaseUnsortedModel::CATEGORY_CODE_FORMS,  BaseUnsortedModel::CATEGORY_CODE_SIP])
        ->setOrder('created_at', HasOrderInterface::SORT_ASC);
    $unsortedCollection = $unsortedService->get($unsortedFiler);
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}
