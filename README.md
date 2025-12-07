# for Cloud Functions Apps

```
# submodule
git submodule add git@github.com:yananob/cloud-functions-common _cf-common

# symbolic links
cp -pv ./_cf-common/.gitignore .
cp -pv ./_cf-common/.gcloudignore .
cp -pv ./_cf-common/.gitattributes .
mkdir -p ./.github/workflows/
cp -rpv ./_cf-common/.github/workflows/ ./.github/workflows/
cp -pv ./_cf-common/test/phpstan.neon .
```

## GitHub actionでのデプロイ

1. 以下見る https://console.cloud.google.com/iam-admin/serviceaccounts/details/103234346909118223436/access?hl=ja&inv=1&invt=Abzjyw&project=nobu5-393106
2. 既存のリポジトリの内容を参考に、同じように追加する

コマンドは以下のようになるはずだが、エラーになるので、上記GUIでの設定にしている
```
gcloud iam service-accounts add-iam-policy-binding \
  "cloud-run-functions-deployer@$nobu5-393106.iam.gserviceaccount.com" \
  --member="principalSet://iam.googleapis.com/projects/1035100650942/locations/global/workloadIdentityPools/github-actions-pool/attribute.repository/yananob/oml-empowered" \ 
  --role="roles/iam.workloadIdentityUser"
```
