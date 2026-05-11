import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { launchImageLibrary } from 'react-native-image-picker';
import AppButton from './AppButton';
import { colors } from '../theme/colors';

export default function ImagePickerField({ label, value, onChange }) {
  const pickImage = async () => {
    const result = await launchImageLibrary({
      mediaType: 'photo',
      quality: 0.8,
      selectionLimit: 1,
    });

    if (result.didCancel || !result.assets?.[0]) {
      return;
    }

    onChange(result.assets[0]);
  };

  return (
    <View style={styles.wrapper}>
      <Text style={styles.label}>{label}</Text>
      <AppButton label={value?.fileName ? 'Change Image' : 'Choose Image'} variant="ghost" onPress={pickImage} />
      {value?.fileName ? <Text style={styles.fileName}>{value.fileName}</Text> : null}
    </View>
  );
}

export function appendImage(formData, field, asset) {
  if (!asset?.uri) {
    return;
  }

  formData.append(field, {
    uri: asset.uri,
    type: asset.type || 'image/jpeg',
    name: asset.fileName || `${field}.jpg`,
  });
}

const styles = StyleSheet.create({
  wrapper: {
    marginBottom: 14,
  },
  label: {
    marginBottom: 6,
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
  },
  fileName: {
    color: colors.slate,
    fontSize: 12,
    marginTop: -6,
    marginBottom: 8,
  },
});
