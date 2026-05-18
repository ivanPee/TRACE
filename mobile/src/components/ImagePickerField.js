import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { launchImageLibrary } from 'react-native-image-picker';
import AppButton from './AppButton';
import { colors } from '../theme/colors';

export default function ImagePickerField({ label, value, onChange, previewUri }) {
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
      {value?.uri || previewUri ? <Image source={{ uri: value?.uri || previewUri }} style={styles.preview} resizeMode="cover" /> : null}
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
  preview: {
    width: '100%',
    height: 180,
    borderRadius: 18,
    marginBottom: 12,
    backgroundColor: colors.line,
  },
  fileName: {
    color: colors.slate,
    fontSize: 12,
    marginTop: -6,
    marginBottom: 8,
  },
});
